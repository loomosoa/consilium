<?php

namespace App\Services;

use App\DTOs\StreamCompleted;
use App\DTOs\StreamToken;
use App\DTOs\UpstreamError;
use App\Models\Generation;

class SseEventFactory
{
    /**
     * Формирует SSE-событие meta.
     */
    public function meta(Generation $generation): string
    {
        $column = $generation->column;
        $model = app(ModelDefinitionService::class)->findByCode($column->model_code);

        return $this->format('meta', [
            'generationId' => $generation->id,
            'columnId' => $column->id,
            'modelCode' => $column->model_code,
            'modelLabel' => $model?->label,
            'status' => $generation->status->value,
        ]);
    }

    /**
     * Формирует SSE-событие token.
     */
    public function token(StreamToken $streamToken): string
    {
        return $this->format('token', [
            'text' => $streamToken->text,
            'sequence' => $streamToken->sequence,
        ]);
    }

    /**
     * Формирует SSE-событие completed.
     */
    public function completed(string $generationId, string $assistantMessageId, StreamCompleted $streamCompleted): string
    {
        return $this->format('completed', [
            'generationId' => $generationId,
            'assistantMessageId' => $assistantMessageId,
            'finishReason' => $streamCompleted->finishReason,
            'promptTokens' => $streamCompleted->promptTokens,
            'completionTokens' => $streamCompleted->completionTokens,
        ]);
    }

    /**
     * Формирует SSE-событие error.
     */
    public function error(string $generationId, UpstreamError $upstreamError): string
    {
        return $this->format('error', [
            'generationId' => $generationId,
            'code' => $upstreamError->code,
            'message' => $upstreamError->message,
            'retryable' => $upstreamError->retryable,
        ]);
    }

    /**
     * Формирует SSE-событие cancelled.
     */
    public function cancelled(string $generationId, ?string $partialOutput): string
    {
        return $this->format('cancelled', [
            'generationId' => $generationId,
            'partialOutput' => $partialOutput,
        ]);
    }

    /**
     * Формирует SSE-событие heartbeat.
     */
    public function heartbeat(): string
    {
        return $this->format('heartbeat', [
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Форматирование SSE-события: event: name\ndata: json\n\n
     */
    private function format(string $event, array $data): string
    {
        return "event: {$event}\ndata: ".json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
    }
}
