<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\StreamCompleted;
use App\DTOs\StreamToken;
use App\DTOs\UpstreamError;
use App\Enums\ColumnStatus;
use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerationService
{
    public function __construct(
        private OpenRouterClient $openRouterClient,
        private ColumnConversationService $conversationService,
        private ModelDefinitionService $modelDefinitionService,
    ) {}

    /**
     * Запускает streaming-запрос к OpenRouter и управляет lifecycle generation.
     *
     * @param  callable(string, mixed): void  $sendSse  — отправка SSE-события клиенту
     * @param  callable(): bool  $clientDisconnected  — проверка отключения клиента
     */
    public function streamGeneration(
        Generation $generation,
        callable $sendSse,
        callable $clientDisconnected,
    ): void {
        $this->transitionToStreaming($generation);

        $context = $this->resolveStreamContext($generation, $sendSse);
        if ($context === null) {
            return;
        }

        $this->executeStream($generation, $context, $sendSse, $clientDisconnected);
    }

    /**
     * Валидирует модель и контекст для стриминга.
     * Возвращает ['model' => ModelDefinition, 'messages' => array] или null (с отправкой ошибки).
     */
    private function resolveStreamContext(Generation $generation, callable $sendSse): ?array
    {
        $model = $this->modelDefinitionService->findByCode($generation->column->model_code);
        if ($model === null) {
            $this->sendStreamError($generation, 'invalid_model', 'Model not found', false, '', $sendSse);

            return null;
        }

        $messages = $this->conversationService->getConfirmedHistory($generation->column);
        if (empty($messages)) {
            $this->sendStreamError($generation, 'empty_context', 'No messages to send', false, '', $sendSse);

            return null;
        }

        return ['model' => $model, 'messages' => $messages];
    }

    /**
     * Отправляет SSE-ошибку: обновляет статус generation и шлёт событие клиенту.
     */
    private function sendStreamError(
        Generation $generation,
        string $code,
        string $message,
        bool $retryable,
        string $partialOutput,
        callable $sendSse,
    ): void {
        $error = new UpstreamError(code: $code, message: $message, retryable: $retryable);
        $this->handleError($generation, $error, $partialOutput);
        $sendSse('error', $error);
    }

    /**
     * Запускает OpenRouter-стрим с подготовленными callback-ами.
     */
    private function executeStream(
        Generation $generation,
        array $context,
        callable $sendSse,
        callable $clientDisconnected,
    ): void {
        $partialOutput = '';
        $callbacks = $this->buildStreamCallbacks($generation, $sendSse, $partialOutput);

        $this->openRouterClient->stream(
            openRouterModelId: $context['model']->openRouterModelId,
            messages: $context['messages'],
            onToken: $callbacks['onToken'],
            onCompleted: $callbacks['onCompleted'],
            onError: $callbacks['onError'],
            onCancel: $callbacks['onCancel'],
            shouldCancel: $clientDisconnected,
        );
    }

    /**
     * Строит callback-и для OpenRouter stream: token, completed, error, cancel.
     */
    private function buildStreamCallbacks(
        Generation $generation,
        callable $sendSse,
        string &$partialOutput,
    ): array {
        return [
            'onToken' => function (StreamToken $token) use ($sendSse, &$partialOutput) {
                $partialOutput .= $token->text;
                $sendSse('token', $token);
            },
            'onCompleted' => function (StreamCompleted $completed) use ($sendSse, $generation, &$partialOutput) {
                $assistantMessageId = $this->handleCompleted($generation, $completed, $partialOutput);
                $sendSse('completed', [
                    'dto' => $completed,
                    'assistantMessageId' => $assistantMessageId,
                ]);
            },
            'onError' => function (UpstreamError $error) use ($sendSse, $generation, &$partialOutput) {
                $this->handleError($generation, $error, $partialOutput);
                $sendSse('error', $error);
            },
            'onCancel' => function () use ($sendSse, $generation, &$partialOutput) {
                $this->handleCancelled($generation, $partialOutput);
                $sendSse('cancelled', ['generationId' => $generation->id, 'partialOutput' => $partialOutput]);
            },
        ];
    }

    /**
     * Переводит generation в streaming и синхронизирует ColumnStatus.
     */
    private function transitionToStreaming(Generation $generation): void
    {
        $generation->update([
            'status' => GenerationStatus::STREAMING,
            'started_at' => now(),
        ]);

        $generation->column->update(['status' => ColumnStatus::STREAMING]);

        Log::info('Generation started streaming', [
            'generation_id' => $generation->id,
            'column_id' => $generation->column_id,
        ]);
    }

    /**
     * Обрабатывает успешное завершение: создаёт assistant message, обновляет статусы.
     */
    private function handleCompleted(Generation $generation, StreamCompleted $completed, string $partialOutput): string
    {
        $assistantMessageId = null;

        DB::transaction(function () use ($generation, $completed, $partialOutput, &$assistantMessageId) {
            $assistantMessage = $this->createAssistantMessage($generation, $partialOutput);
            $assistantMessageId = $assistantMessage->id;

            $this->markGenerationCompleted($generation, $completed, $partialOutput);
            $this->markColumnCompleted($generation);

            Log::info('Generation completed', [
                'generation_id' => $generation->id,
                'assistant_message_id' => $assistantMessage->id,
                'finish_reason' => $completed->finishReason,
            ]);
        });

        return $assistantMessageId;
    }

    /**
     * Обновляет статус generation на completed, сохраняет токены и partial_output.
     */
    private function markGenerationCompleted(Generation $generation, StreamCompleted $completed, string $partialOutput): void
    {
        $generation->update([
            'status' => GenerationStatus::COMPLETED,
            'partial_output' => $partialOutput,
            'prompt_tokens' => $completed->promptTokens,
            'completion_tokens' => $completed->completionTokens,
            'completed_at' => now(),
        ]);
    }

    /**
     * Обновляет статус колонки на completed после успешной generation.
     */
    private function markColumnCompleted(Generation $generation): void
    {
        $generation->column->update([
            'status' => ColumnStatus::COMPLETED,
            'last_generation_id' => $generation->id,
        ]);
    }

    /**
     * Обрабатывает ошибку: сохраняет partialOutput, обновляет статусы.
     */
    private function handleError(Generation $generation, UpstreamError $error, string $partialOutput): void
    {
        DB::transaction(function () use ($generation, $error, $partialOutput) {
            $generation->update([
                'status' => GenerationStatus::ERROR,
                'partial_output' => $partialOutput,
                'error_code' => $error->code,
                'error_message' => $error->message,
                'retryable' => $error->retryable,
                'completed_at' => now(),
            ]);

            $generation->column->update([
                'status' => ColumnStatus::ERROR,
                'last_error_code' => $error->code,
                'last_error_message' => $error->message,
            ]);

            Log::error('Generation failed', [
                'generation_id' => $generation->id,
                'error_code' => $error->code,
                'retryable' => $error->retryable,
            ]);
        });
    }

    /**
     * Обрабатывает отмену: сохраняет partialOutput, НЕ создаёт assistant message.
     */
    private function handleCancelled(Generation $generation, string $partialOutput): void
    {
        DB::transaction(function () use ($generation, $partialOutput) {
            $generation->update([
                'status' => GenerationStatus::CANCELLED,
                'partial_output' => $partialOutput,
                'completed_at' => now(),
            ]);

            $generation->column->update([
                'status' => ColumnStatus::CANCELLED,
            ]);

            Log::info('Generation cancelled', [
                'generation_id' => $generation->id,
                'partial_output_length' => mb_strlen($partialOutput),
            ]);
        });
    }

    /**
     * Отменяет generation: переводит в cancelled, сохраняет partialOutput.
     */
    public function cancelGeneration(Generation $generation): void
    {
        $this->handleCancelled($generation, $generation->partial_output ?? '');
    }

    /**
     * Создаёт новую generation для retry на основе последнего user message.
     *
     * @throws \InvalidArgumentException если generation не в статусе error/cancelled
     */
    public function retryGeneration(Generation $failedGeneration): Generation
    {
        $this->assertRetryable($failedGeneration);

        return DB::transaction(function () use ($failedGeneration) {
            $column = $failedGeneration->column;
            $this->assertColumnHasNoActiveGeneration($column);

            return $this->createPendingGeneration($failedGeneration, $column);
        });
    }

    /**
     * Проверяет, что generation допускает retry (статус error или cancelled).
     */
    private function assertRetryable(Generation $generation): void
    {
        if (! $generation->status->isRetryable()) {
            throw new \InvalidArgumentException(
                "Retry is only allowed for error or cancelled generations, got: {$generation->status->value}"
            );
        }
    }

    /**
     * Проверяет, что в колонке нет активной generation (с pessimistic lock).
     */
    private function assertColumnHasNoActiveGeneration(ColumnConversation $column): void
    {
        $activeGeneration = Generation::where('column_id', $column->id)
            ->active()
            ->lockForUpdate()
            ->first();

        if ($activeGeneration !== null) {
            throw new \InvalidArgumentException('Column already has an active generation');
        }
    }

    /**
     * Создаёт PENDING generation и обновляет статус колонки на WAITING.
     */
    private function createPendingGeneration(Generation $failedGeneration, ColumnConversation $column): Generation
    {
        $newGeneration = Generation::create([
            'column_id' => $failedGeneration->column_id,
            'user_message_id' => $failedGeneration->user_message_id,
            'status' => GenerationStatus::PENDING,
        ]);

        $column->update([
            'status' => ColumnStatus::WAITING,
            'last_generation_id' => $newGeneration->id,
            'last_error_code' => null,
            'last_error_message' => null,
        ]);

        Log::info('Generation retry created', [
            'failed_generation_id' => $failedGeneration->id,
            'new_generation_id' => $newGeneration->id,
            'column_id' => $column->id,
        ]);

        return $newGeneration;
    }

    /**
     * Создаёт Message(role=assistant) с привязкой к generation.
     */
    private function createAssistantMessage(Generation $generation, string $content): Message
    {
        $lastSequence = $generation->column->messages()
            ->max('sequence') ?? 0;

        return Message::create([
            'column_id' => $generation->column_id,
            'role' => MessageRole::ASSISTANT,
            'content' => $content,
            'sequence' => $lastSequence + 1,
            'generation_id' => $generation->id,
            'created_at' => now(),
        ]);
    }
}
