<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Services\GenerationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerationCancelController
{
    public function __construct(
        private GenerationService $generationService,
    ) {}

    /**
     * POST /api/generations/{generationId}/cancel
     *
     * Принудительная остановка стриминга по инициативе пользователя.
     * Прерывает upstream-запрос к OpenRouter, переводит generation в cancelled,
     * сохраняет partialOutput без создания Message(role=assistant).
     */
    public function cancel(Request $request, string $generationId): JsonResponse
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

        $this->generationService->cancelGeneration($generation);

        return response()->json([
            'generation' => $generation->refresh()->toArray(),
        ]);
    }
}
