<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\UpstreamError;
use App\Models\Generation;
use App\Services\GenerationService;
use App\Services\SseEventFactory;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $generation = $this->resolveGeneration($generationId);
        if ($generation === null) {
            return response()->json(['message' => 'Generation not found'], 404);
        }

        if (! $generation->status->isActive()) {
            return response()->json(['message' => 'Generation is not active'], 422);
        }

        return $this->createStreamedResponse($generation);
    }

    /**
     * Ищет generation по ID, возвращает null если не найден.
     */
    private function resolveGeneration(string $generationId): ?Generation
    {
        try {
            return Generation::find($generationId);
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * Создаёт StreamedResponse с SSE-заголовками и callback-ом стриминга.
     */
    private function createStreamedResponse(Generation $generation): StreamedResponse
    {
        return new StreamedResponse(
            function () use ($generation) {
                $this->executeStreamCallback($generation);
            },
            200,
            $this->sseHeaders(),
        );
    }

    /**
     * Основной callback стриминга: освобождение сессии, meta, запуск generation.
     */
    private function executeStreamCallback(Generation $generation): void
    {
        $this->releaseSessionLock();

        try {
            if (connection_aborted()) {
                return;
            }

            echo $this->sseEventFactory->meta($generation);

            $lastHeartbeat = time();
            $sendSse = $this->buildSendSse($generation->id);
            $clientDisconnected = $this->buildClientDisconnected($lastHeartbeat);

            $this->generationService->streamGeneration($generation, $sendSse, $clientDisconnected);
        } catch (\Throwable) {
            echo $this->sseEventFactory->error($generation->id, new UpstreamError(
                code: 'internal_error',
                message: 'Internal server error',
                retryable: true,
            ));
        }
    }

    /**
     * Освобождает lock сессии до начала долгого стриминга.
     * Для database-драйвера close() — no-op, lock держится через PDO-соединение.
     * Поэтому отключаем DB-соединение: это закрывает PDO и снимает row-level lock.
     * Laravel автоматически переподключится при следующих запросах к БД.
     */
    private function releaseSessionLock(): void
    {
        session()->save();
        session()->getHandler()->close();
        DB::connection()->disconnect();
    }

    /**
     * Строит callback отправки SSE-событий клиенту.
     */
    private function buildSendSse(string $generationId): callable
    {
        return function (string $type, mixed $data) use ($generationId) {
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

            $this->flushEvent($event);
        };
    }

    /**
     * Строит callback проверки отключения клиента с heartbeat.
     */
    private function buildClientDisconnected(int &$lastHeartbeat): callable
    {
        return function () use (&$lastHeartbeat) {
            if (time() - $lastHeartbeat >= self::HEARTBEAT_INTERVAL_SECONDS) {
                $this->flushEvent($this->sseEventFactory->heartbeat());
                $lastHeartbeat = time();
            }

            return connection_aborted() !== 0;
        };
    }

    /**
     * Отправляет SSE-событие в output и сбрасывает буферы.
     */
    private function flushEvent(string $event): void
    {
        if ($event === '') {
            return;
        }

        echo $event;
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Возвращает HTTP-заголовки для SSE-ответа.
     */
    private function sseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }
}
