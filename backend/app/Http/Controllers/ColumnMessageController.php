<?php

namespace App\Http\Controllers;

use App\Enums\ColumnStatus;
use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Http\Requests\CreateColumnMessageRequest;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ColumnMessageController extends Controller
{
    public function store(CreateColumnMessageRequest $request, string $columnId): JsonResponse
    {
        $column = ColumnConversation::find($columnId);

        if ($column === null) {
            return response()->json(['message' => 'Column not found'], 404);
        }

        if ($this->hasActiveGeneration($column)) {
            return response()->json(
                ['message' => 'Column already has an active generation'],
                409,
            );
        }

        $prompt = $request->validated('prompt');

        $userMessage = $this->createUserMessage($column, $prompt);
        $generation = $this->createGeneration($column, $userMessage);

        $this->updateColumnAfterGeneration($column, $generation);

        Log::info('Follow-up message created', [
            'column_id' => $column->id,
            'generation_id' => $generation->id,
            'prompt_length' => mb_strlen($prompt),
        ]);

        return response()->json([
            'columnId' => $column->id,
            'generation' => $this->toGenerationDto($generation),
        ], 201);
    }

    private function hasActiveGeneration(ColumnConversation $column): bool
    {
        return $column->generations()
            ->whereIn('status', [GenerationStatus::PENDING, GenerationStatus::STREAMING])
            ->exists();
    }

    private function createUserMessage(ColumnConversation $column, string $prompt): Message
    {
        $maxSequence = $column->messages()->max('sequence') ?? 0;

        return Message::create([
            'column_id' => $column->id,
            'role' => MessageRole::USER,
            'content' => $prompt,
            'sequence' => $maxSequence + 1,
        ]);
    }

    private function createGeneration(ColumnConversation $column, Message $userMessage): Generation
    {
        return Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $userMessage->id,
            'status' => GenerationStatus::PENDING,
        ]);
    }

    private function updateColumnAfterGeneration(ColumnConversation $column, Generation $generation): void
    {
        $column->update([
            'last_generation_id' => $generation->id,
            'status' => ColumnStatus::WAITING,
        ]);
    }

    private function toGenerationDto(Generation $generation): array
    {
        return [
            'id' => $generation->id,
            'columnId' => $generation->column_id,
            'userMessageId' => $generation->user_message_id,
            'status' => $generation->status->value,
        ];
    }
}
