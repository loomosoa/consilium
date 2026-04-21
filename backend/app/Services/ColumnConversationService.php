<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Models\ColumnConversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class ColumnConversationService
{
    public function __construct(
        private ModelDefinitionService $modelDefinitionService,
    ) {}

    /**
     * Возвращает подтверждённую историю сообщений колонки,
     * ограниченную contextWindow модели.
     *
     * - Только сообщения данной колонки (изоляция)
     * - Только подтверждённые: user messages + assistant messages от completed generations
     * - partialOutput отменённых/ошибочных generation НЕ включается
     * - Автоматическое отсечение старых сообщений при превышении contextWindow
     *
     * @return Message[]
     */
    public function getConfirmedHistory(ColumnConversation $column): array
    {
        $messages = $this->fetchConfirmedMessages($column);

        return $this->trimToContextWindow($messages, $column->model_code);
    }

    private function fetchConfirmedMessages(ColumnConversation $column): array
    {
        return $column->messages()
            ->where(function ($query) {
                $query->where('role', MessageRole::USER->value)
                    ->orWhere(function ($q) {
                        $q->where('role', MessageRole::ASSISTANT->value)
                            ->whereHas('generation', fn ($g) => $g
                                ->where('status', GenerationStatus::COMPLETED->value)
                            );
                    });
            })
            ->orderBy('sequence')
            ->get()
            ->all();
    }

    /**
     * Отсекает самые старые сообщения, если суммарная длина
     * превышает contextWindow модели. Без ошибки пользователю.
     *
     * Стратегия: считаем символы (1 токен ≈ 2 символа для среднего текста), отсекаем с начала.
     * TODO: Заменить на точный подсчёт токенов через tiktoken при интеграции с OpenRouter (Эпик 7).
     *
     * @param  Message[]  $messages
     * @return Message[]
     */
    private function trimToContextWindow(array $messages, string $modelCode): array
    {
        $model = $this->modelDefinitionService->findByCode($modelCode);

        if ($model === null) {
            return $messages;
        }

        $maxChars = $model->contextWindow * 2;
        $totalChars = array_sum(array_map(fn (Message $m) => mb_strlen($m->content), $messages));

        if ($totalChars <= $maxChars) {
            return $messages;
        }

        // Вычисляем индекс отсечения (O(n) вместо O(n²))
        $cutIndex = 0;
        while ($cutIndex < count($messages) && $totalChars > $maxChars) {
            $totalChars -= mb_strlen($messages[$cutIndex]->content);
            $cutIndex++;
        }

        $trimmedMessages = array_slice($messages, $cutIndex);

        // Защита от пустого контекста после тримминга
        if (count($trimmedMessages) === 0) {
            Log::warning('All messages trimmed due to context window', [
                'model_code' => $modelCode,
                'context_window' => $model->contextWindow,
            ]);

            throw new \RuntimeException('Context window too small for any message.');
        }

        return $trimmedMessages;
    }
}
