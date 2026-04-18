<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Services\GenerationService;
use App\Services\SseEventFactory;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerationStreamController
{
    private const HEARTBEAT_INTERVAL_SECONDS = 15;

    public function __construct(
        private GenerationService $generationService,
        private SseEventFactory $sseEventFactory,
    ) {}

    /**
     * GET /api/generations/{generationId}/stream
     *
     * SSE-ответ: запуск потока из OpenRouter, трансляция токенов,
     * heartbeat каждые 15 секунд, обработка Connection Closed.
     */
    public function stream(Request $request, string $generationId): StreamedResponse|JsonResponse
    {
        try {
            $generation = Generation::find($generationId);
        } catch (QueryException) {
            return response()->json(['message' => 'Generation not found'], 404);
        }

        if ($generation === null) {
            return response()->json(['message' => 'Generation not found'], 404);
        }

        if (! $generation->status->isActive()) {
            return response()->json(['message' => 'Generation is not active'], 422);
        }

        return new StreamedResponse(
            function () use ($generation) {
                // Отправляем заголовок SSE (уже отправлен StreamedResponse)
                if (connection_aborted()) {
                    return;
                }

                // Meta-событие
                echo $this->sseEventFactory->meta($generation);

                $lastHeartbeat = time();
                $generationId = $generation->id;

                $sendSse = function (string $type, mixed $data) use ($generationId) {
                    $event = match ($type) {
                        'token' => $this->sseEventFactory->token($data),
                        'completed' => $this->sseEventFactory->completed(
                            $generationId,
                            $data['assistantMessageId'],
                            $data['dto'],
                        ),
                        'error' => $this->sseEventFactory->error($generationId, $data),
                        'cancelled' => $this->sseEventFactory->cancelled(
                            $generationId,
                            $data['partialOutput'] ?? null,
                        ),
                        default => '',
                    };

                    if ($event !== '') {
                        echo $event;
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                };

                $clientDisconnected = function () use (&$lastHeartbeat) {
                    // Heartbeat
                    if (time() - $lastHeartbeat >= self::HEARTBEAT_INTERVAL_SECONDS) {
                        echo $this->sseEventFactory->heartbeat();
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                        $lastHeartbeat = time();
                    }

                    return connection_aborted() !== 0;
                };

                $this->generationService->streamGeneration(
                    $generation,
                    $sendSse,
                    $clientDisconnected,
                );
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }
}
