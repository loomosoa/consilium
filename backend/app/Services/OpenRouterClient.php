<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\StreamCompleted;
use App\DTOs\StreamToken;
use App\DTOs\UpstreamError;
use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

class OpenRouterClient
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    private const TIMEOUT_SECONDS = 300;

    public function __construct(
        private ApiKeyResolver $apiKeyResolver,
        private ErrorMapper $errorMapper,
    ) {}

    /**
     * Отправляет streaming-запрос к OpenRouter и вызывает callback
     * для каждого токена/события.
     *
     * @param  Message[]  $messages  — подтверждённая история колонки
     * @param  callable(StreamToken): void  $onToken
     * @param  callable(StreamCompleted): void  $onCompleted
     * @param  callable(UpstreamError): void  $onError
     * @param  callable(): void  $onCancel  — вызывается при прерывании
     */
    public function stream(
        string $openRouterModelId,
        array $messages,
        callable $onToken,
        callable $onCompleted,
        callable $onError,
        callable $onCancel,
        ?callable $shouldCancel = null,
    ): void {
        if (empty($openRouterModelId)) {
            throw new \InvalidArgumentException('openRouterModelId cannot be empty');
        }

        $apiKey = $this->apiKeyResolver->resolve();
        $payload = $this->buildPayload($openRouterModelId, $messages);

        $response = $this->sendStreamRequest($apiKey, $payload, $openRouterModelId, $onError);
        if ($response === null) {
            return;
        }

        if ($response->failed()) {
            $onError($this->mapHttpError($response->status(), $this->readFullBody($response)));

            return;
        }

        $this->handleStreamResponse($response, $onToken, $onCompleted, $onError, $onCancel, $shouldCancel);
    }

    /**
     * Выполняет HTTP-запрос к OpenRouter со streaming.
     * Возвращает Response или null (если ConnectionException — ошибка уже отправлена).
     */
    private function sendStreamRequest(
        string $apiKey,
        array $payload,
        string $openRouterModelId,
        callable $onError,
    ): ?Response {
        Log::info('OpenRouter request', [
            'model' => $openRouterModelId,
            'messages_count' => count($payload['messages']),
        ]);

        try {
            return $this->buildRequest($apiKey)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withOptions(['stream' => true])
                ->post(self::BASE_URL.'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            $onError($this->errorMapper->map('connection_error', $e->getMessage()));

            return null;
        }
    }

    /**
     * Обрабатывает успешный HTTP-ответ: запускает обработку SSE-потока.
     */
    private function handleStreamResponse(
        Response $response,
        callable $onToken,
        callable $onCompleted,
        callable $onError,
        callable $onCancel,
        ?callable $shouldCancel,
    ): void {
        try {
            $this->processStream(
                $response->toPsrResponse()->getBody(),
                $onToken,
                $onCompleted,
                $onError,
                $onCancel,
                $shouldCancel,
            );
        } catch (ConnectionException $e) {
            $onError($this->errorMapper->map('connection_error', $e->getMessage()));
        } catch (\Throwable $e) {
            Log::error('Unexpected error during stream processing', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            $onError($this->errorMapper->map('upstream_error', $e->getMessage()));
        }
    }

    /**
     * Формирует payload для OpenRouter API.
     *
     * @param  Message[]  $messages
     */
    private function buildPayload(string $openRouterModelId, array $messages): array
    {
        return [
            'model' => $openRouterModelId,
            'stream' => true,
            'messages' => array_map(fn (Message $m) => [
                'role' => $m->role->value,
                'content' => $m->content,
            ], $messages),
        ];
    }

    private function buildRequest(string $apiKey): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url', 'http://localhost'),
            'X-Title' => 'Consilium',
        ]);
    }

    /**
     * Читает SSE-поток из PSR-7 StreamInterface по чанкам
     * и вызывает callback-и по мере поступления данных.
     */
    private function processStream(
        StreamInterface $stream,
        callable $onToken,
        callable $onCompleted,
        callable $onError,
        callable $onCancel,
        ?callable $shouldCancel,
    ): void {
        $buffer = '';
        $tokenSequence = 0;

        while (! $stream->eof()) {
            if ($this->shouldCancelStream($shouldCancel, $onCancel)) {
                return;
            }

            $chunk = $stream->read(8192);
            if ($chunk === '') {
                usleep(10_000);
                continue;
            }

            $buffer .= $chunk;
            $tokenSequence = $this->processBufferLines(
                $buffer, $tokenSequence, $onToken, $onCompleted, $onError, $onCancel, $shouldCancel,
            );

            if ($tokenSequence === -1) {
                return; // parse error / completed / cancel — обработчик уже вызван
            }
        }

        $this->handleStreamInterrupted($tokenSequence, $onError);
    }

    /**
     * Проверяет, нужно ли прервать стрим по запросу клиента.
     */
    private function shouldCancelStream(?callable $shouldCancel, callable $onCancel): bool
    {
        if ($shouldCancel !== null && $shouldCancel()) {
            $onCancel();

            return true;
        }

        return false;
    }

    /**
     * Обрабатывает все полные строки в буфере.
     * Возвращает обновлённый tokenSequence или -1 если стрим завершён (completed/error/cancel).
     */
    private function processBufferLines(
        string &$buffer,
        int $tokenSequence,
        callable $onToken,
        callable $onCompleted,
        callable $onError,
        callable $onCancel,
        ?callable $shouldCancel,
    ): int {
        while (($newlinePos = strpos($buffer, "\n")) !== false) {
            if ($shouldCancel !== null && $shouldCancel()) {
                $onCancel();

                return -1;
            }

            $line = substr($buffer, 0, $newlinePos);
            $buffer = substr($buffer, $newlinePos + 1);

            $result = $this->parseSseLine(trim($line), $tokenSequence, $onToken, $onCompleted, $onError);
            if ($result === -1) {
                return -1;
            }

            $tokenSequence = $result;
        }

        return $tokenSequence;
    }

    /**
     * Разбирает одну SSE-строку и вызывает соответствующий callback.
     * Возвращает обновлённый tokenSequence или -1 если стрим завершён.
     */
    private function parseSseLine(
        string $line,
        int $tokenSequence,
        callable $onToken,
        callable $onCompleted,
        callable $onError,
    ): int {
        if ($line === '' || $line === 'data: [DONE]') {
            return $tokenSequence;
        }

        if (! str_starts_with($line, 'data: ')) {
            return $tokenSequence;
        }

        $data = json_decode(substr($line, 6), true);
        if ($data === null) {
            $onError($this->errorMapper->map('stream_parse_error', 'Failed to parse stream event'));

            return -1;
        }

        if (isset($data['error'])) {
            $onError($this->errorMapper->mapFromOpenRouter($data['error']));

            return -1;
        }

        return $this->dispatchStreamEvent($data, $tokenSequence, $onToken, $onCompleted);
    }

    /**
     * Диспатчит SSE-событие (token или completed) по данным из choices[0].
     * Возвращает обновлённый tokenSequence или -1 если completed.
     */
    private function dispatchStreamEvent(
        array $data,
        int $tokenSequence,
        callable $onToken,
        callable $onCompleted,
    ): int {
        if (! isset($data['choices'][0])) {
            return $tokenSequence;
        }

        $choice = $data['choices'][0];
        $finishReason = $choice['finish_reason'] ?? null;

        if ($finishReason !== null) {
            $usage = $data['usage'] ?? null;
            $onCompleted(new StreamCompleted(
                finishReason: $finishReason,
                promptTokens: $usage['prompt_tokens'] ?? null,
                completionTokens: $usage['completion_tokens'] ?? null,
            ));

            return -1;
        }

        $delta = $choice['delta'] ?? null;
        if ($delta !== null && isset($delta['content']) && $delta['content'] !== '') {
            $tokenSequence++;
            $onToken(new StreamToken(
                text: $delta['content'],
                sequence: $tokenSequence,
            ));
        }

        return $tokenSequence;
    }

    /**
     * Обрабатывает обрыв потока без finish_reason.
     */
    private function handleStreamInterrupted(int $tokenSequence, callable $onError): void
    {
        if ($tokenSequence > 0) {
            Log::warning('Stream ended without finish_reason', [
                'tokens_received' => $tokenSequence,
            ]);
            $onError($this->errorMapper->map('stream_interrupted', 'Stream ended without finish_reason'));
        }
    }

    /**
     * Полностью читает тело ответа (для error-path, где стриминг не нужен).
     */
    private function readFullBody(Response $response): string
    {
        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof()) {
            $body .= $stream->read(65536);
        }

        return $body;
    }

    private function mapHttpError(int $status, string $body): UpstreamError
    {
        Log::warning('OpenRouter HTTP error', [
            'status' => $status,
            'body' => substr($body, 0, 200),
        ]);

        return match (true) {
            $status === 429 => $this->errorMapper->map('rate_limit', 'Rate limit exceeded'),
            $status >= 500 => $this->errorMapper->map('provider_unavailable', 'Provider temporarily unavailable'),
            $status === 401 => $this->errorMapper->map('auth_error', 'Invalid API key'),
            default => $this->errorMapper->map('upstream_error', "Upstream error: HTTP {$status}"),
        };
    }
}
