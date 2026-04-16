<?php

namespace App\Services;

use App\DTOs\ColumnDto;
use App\DTOs\GenerationDto;
use App\DTOs\WorkspaceResponse;
use App\Enums\ColumnStatus;
use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Enums\WorkspaceState;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class WorkspaceService
{
    public function __construct(
        private ModelDefinitionService $modelDefinitionService,
    ) {}

    public function create(string $sessionId, string $initialPrompt): WorkspaceResponse
    {
        $this->validatePromptLength($initialPrompt);

        $models = $this->modelDefinitionService->active();

        return DB::transaction(function () use ($sessionId, $initialPrompt, $models) {
            $workspace = Workspace::create([
                'session_id' => $sessionId,
                'initial_prompt' => $initialPrompt,
                'state' => WorkspaceState::ACTIVE,
            ]);

            $columnDtos = [];
            $generationDtos = [];

            foreach ($models as $model) {
                $column = ColumnConversation::create([
                    'workspace_id' => $workspace->id,
                    'model_code' => $model->code,
                    'position' => $model->order,
                    'status' => ColumnStatus::WAITING,
                ]);

                $userMessage = Message::create([
                    'column_id' => $column->id,
                    'role' => MessageRole::USER,
                    'content' => $initialPrompt,
                    'sequence' => 1,
                ]);

                $generation = Generation::create([
                    'column_id' => $column->id,
                    'user_message_id' => $userMessage->id,
                    'status' => GenerationStatus::PENDING,
                ]);

                $column->update(['last_generation_id' => $generation->id]);
                $column->refresh();

                $columnDtos[] = new ColumnDto(
                    id: $column->id,
                    modelCode: $column->model_code,
                    position: $column->position,
                    status: $column->status->value,
                );

                $generationDtos[] = new GenerationDto(
                    id: $generation->id,
                    columnId: $generation->column_id,
                    userMessageId: $generation->user_message_id,
                    status: $generation->status->value,
                );
            }

            return new WorkspaceResponse(
                workspaceId: $workspace->id,
                columns: $columnDtos,
                generations: $generationDtos,
            );
        });
    }

    public function find(string $workspaceId): ?WorkspaceResponse
    {
        $workspace = Workspace::with('columns.messages', 'columns.generations')
            ->find($workspaceId);

        if ($workspace === null) {
            return null;
        }

        $columnDtos = [];
        $generationDtos = [];

        foreach ($workspace->columns as $column) {
            $columnDtos[] = new ColumnDto(
                id: $column->id,
                modelCode: $column->model_code,
                position: $column->position,
                status: $column->status->value,
            );

            foreach ($column->generations as $generation) {
                $generationDtos[] = new GenerationDto(
                    id: $generation->id,
                    columnId: $generation->column_id,
                    userMessageId: $generation->user_message_id,
                    status: $generation->status->value,
                );
            }
        }

        return new WorkspaceResponse(
            workspaceId: $workspace->id,
            columns: $columnDtos,
            generations: $generationDtos,
        );
    }

    public function smallestContextWindow(): int
    {
        return $this->modelDefinitionService->smallestContextWindow();
    }

    private function validatePromptLength(string $prompt): void
    {
        $maxChars = $this->smallestContextWindow() * 4;

        if (mb_strlen($prompt) > $maxChars) {
            throw new \InvalidArgumentException(
                "Prompt exceeds maximum allowed length of {$maxChars} characters."
            );
        }
    }
}
