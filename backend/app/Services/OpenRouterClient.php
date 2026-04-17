<?php

namespace App\Services;

use App\DTOs\StreamCompleted;
use App\DTOs\StreamToken;
use App\DTOs\UpstreamError;
use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterClient
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    private const TIMEOUT_SECONDS = 120;

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

        Log::info('OpenRouter request', [
            'model' => $openRouterModelId,
            'messages_count' => count($messages),
        ]);

        try {
            $response = $this->buildRequest($apiKey)
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::BASE_URL.'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            $onError($this->errorMapper->map('connection_error', $e->getMessage()));

            return;
        }

        if ($response->failed()) {
            $onError($this->mapHttpError($response->status(), $response->body()));

            return;
        }

        $body = $response->body();
        $this->processStream($body, $onToken, $onCompleted, $onError, $onCancel, $shouldCancel);
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
     * Обрабатывает SSE-поток от OpenRouter.
     */
    private function processStream(
        string $body,
        callable $onToken,
        callable $onCompleted,
        callable $onError,
        callable $onCancel,
        ?callable $shouldCancel,
    ): void {
        $lines = explode("\n", $body);
        $tokenSequence = 0;

        foreach ($lines as $line) {
            // Проверка cancel
            if ($shouldCancel !== null && $shouldCancel()) {
                $onCancel();

                return;
            }

            $line = trim($line);

            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }

            if (! str_starts_with($line, 'data: ')) {
                continue;
            }

            $json = substr($line, 6);
            $data = json_decode($json, true);

            if ($data === null) {
                $onError($this->errorMapper->map('stream_parse_error', 'Failed to parse stream event'));

                return;
            }

            // Проверка на ошибку в потоке
            if (isset($data['error'])) {
                $onError($this->errorMapper->mapFromOpenRouter($data['error']));

                return;
            }

            // Обработка выбора (choice)
            if (! isset($data['choices'][0])) {
                continue;
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

                return;
            }

            // Токен
            $delta = $choice['delta'] ?? null;
            if ($delta !== null && isset($delta['content']) && $delta['content'] !== '') {
                $tokenSequence++;
                $onToken(new StreamToken(
                    text: $delta['content'],
                    sequence: $tokenSequence,
                ));
            }
        }

        // Проверка на обрыв потока без завершения
        if ($tokenSequence > 0) {
            Log::warning('Stream ended without finish_reason', [
                'tokens_received' => $tokenSequence,
            ]);
        }
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
