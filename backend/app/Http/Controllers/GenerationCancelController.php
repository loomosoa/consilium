<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Services\GenerationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        Log::info('Generation cancel requested', [
            'generation_id' => $generationId,
            'status' => $generation->status->value,
            'column_id' => $generation->column_id,
        ]);

        $this->generationService->cancelGeneration($generation);

        return response()->json([
            'generation' => $generation->refresh()->toArray(),
        ]);
    }
}
