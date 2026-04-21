<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\UpstreamError;

class ErrorMapper
{
    private const ERROR_MAP = [
        'rate_limit' => [
            'message' => 'Rate limit exceeded. Please try again in a moment.',
            'retryable' => true,
        ],
        'provider_unavailable' => [
            'message' => 'The AI provider is temporarily unavailable. Please try again.',
            'retryable' => true,
        ],
        'timeout' => [
            'message' => 'The request timed out. Please try again.',
            'retryable' => true,
        ],
        'auth_error' => [
            'message' => 'Invalid API key. Please check your configuration.',
            'retryable' => false,
        ],
        'stream_parse_error' => [
            'message' => 'Received malformed response from provider.',
            'retryable' => true,
        ],
        'connection_error' => [
            'message' => 'Unable to connect to the AI provider.',
            'retryable' => true,
        ],
        'context_exceeded' => [
            'message' => 'The conversation is too long for this model.',
            'retryable' => false,
        ],
        'stream_interrupted' => [
            'message' => 'The response was interrupted. Please try again.',
            'retryable' => true,
        ],
        'upstream_error' => [
            'message' => 'An unexpected error occurred.',
            'retryable' => true,
        ],
    ];

    /**
     * Преобразует внутренний код ошибки в безопасное пользовательское сообщение.
     */
    public function map(string $code, string $fallbackMessage = ''): UpstreamError
    {
        $definition = self::ERROR_MAP[$code] ?? null;

        if ($definition === null) {
            return new UpstreamError(
                code: 'upstream_error',
                message: $fallbackMessage ?: 'An unexpected error occurred.',
                retryable: true,
            );
        }

        return new UpstreamError(
            code: $code,
            message: $definition['message'],
            retryable: $definition['retryable'],
        );
    }

    /**
     * Преобразует ошибку из ответа OpenRouter API.
     */
    public function mapFromOpenRouter(array $error): UpstreamError
    {
        $code = $error['code'] ?? 'upstream_error';
        $message = $error['message'] ?? 'Unknown error from provider.';

        // Маппинг известных кодов OpenRouter
        $mappedCode = match ($code) {
            '429' => 'rate_limit',
            'rate_limit' => 'rate_limit',
            'context_length_exceeded' => 'context_exceeded',
            'insufficient_quota' => 'rate_limit',
            'server_error' => 'provider_unavailable',
            default => 'upstream_error',
        };

        return $this->map($mappedCode, $message);
    }
}
