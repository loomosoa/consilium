<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Services\GenerationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GenerationRetryController
{
    public function __construct(
        private GenerationService $generationService,
    ) {}

    /**
     * POST /api/generations/{generationId}/retry
     *
     * Повторный запуск на основе последнего user message
     * и только подтверждённой истории колонки.
     * partialOutput предыдущей generation не попадает в контекст.
     */
    public function retry(Request $request, string $generationId): JsonResponse
    {
        try {
            $generation = Generation::find($generationId);
        } catch (QueryException) {
            return response()->json(['message' => 'Generation not found'], 404);
        }

        if ($generation === null) {
            return response()->json(['message' => 'Generation not found'], 404);
        }

        if ($generation->status->isActive()) {
            // Клиент потерял SSE-соединение, но generation ещё стримится — отменяем
            $this->generationService->cancelGeneration($generation);
            $generation->refresh();
        }

        if (! $generation->status->isRetryable()) {
            return response()->json([
                'message' => 'Retry is only allowed for error or cancelled generations',
            ], 422);
        }

        try {
            $newGeneration = $this->generationService->retryGeneration($generation);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Log::info('Generation retry requested', [
            'original_generation_id' => $generationId,
            'new_generation_id' => $newGeneration->id,
        ]);

        return response()->json([
            'generation' => $newGeneration->toArray(),
        ], 201);
    }
}
