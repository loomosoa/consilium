<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\Log;

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
            $workspace = $this->createWorkspace($sessionId, $initialPrompt);

            $columnDtos = [];
            $generationDtos = [];

            foreach ($models as $model) {
                [$columnDto, $generationDto] = $this->createColumnWithGeneration(
                    $workspace->id,
                    $model->code,
                    $model->order,
                    $initialPrompt,
                );

                $columnDtos[] = $columnDto;
                $generationDtos[] = $generationDto;
            }

            $response = $this->buildResponse($workspace->id, $columnDtos, $generationDtos);
            $this->logCreation($sessionId, $initialPrompt, $response);

            return $response;
        });
    }

    public function find(string $workspaceId): ?WorkspaceResponse
    {
        $workspace = Workspace::with('columns.generations')
            ->find($workspaceId);

        if ($workspace === null) {
            return null;
        }

        return $this->mapWorkspaceToResponse($workspace);
    }

    private function createWorkspace(string $sessionId, string $initialPrompt): Workspace
    {
        return Workspace::create([
            'session_id' => $sessionId,
            'initial_prompt' => $initialPrompt,
            'state' => WorkspaceState::ACTIVE,
        ]);
    }

    private function createColumnWithGeneration(
        string $workspaceId,
        string $modelCode,
        int $position,
        string $initialPrompt,
    ): array {
        $column = ColumnConversation::create([
            'workspace_id' => $workspaceId,
            'model_code' => $modelCode,
            'position' => $position,
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

        return [
            $this->toColumnDto($column),
            $this->toGenerationDto($generation),
        ];
    }

    private function mapWorkspaceToResponse(Workspace $workspace): WorkspaceResponse
    {
        $columnDtos = [];
        $generationDtos = [];

        foreach ($workspace->columns as $column) {
            $columnDtos[] = $this->toColumnDto($column);

            foreach ($column->generations as $generation) {
                $generationDtos[] = $this->toGenerationDto($generation);
            }
        }

        return $this->buildResponse($workspace->id, $columnDtos, $generationDtos);
    }

    private function toColumnDto(ColumnConversation $column): ColumnDto
    {
        return new ColumnDto(
            id: $column->id,
            modelCode: $column->model_code,
            position: $column->position,
            status: $column->status->value,
        );
    }

    private function toGenerationDto(Generation $generation): GenerationDto
    {
        return new GenerationDto(
            id: $generation->id,
            columnId: $generation->column_id,
            userMessageId: $generation->user_message_id,
            status: $generation->status->value,
        );
    }

    private function buildResponse(
        string $workspaceId,
        array $columnDtos,
        array $generationDtos,
    ): WorkspaceResponse {
        return new WorkspaceResponse(
            workspaceId: $workspaceId,
            columns: $columnDtos,
            generations: $generationDtos,
        );
    }

    private function logCreation(string $sessionId, string $initialPrompt, WorkspaceResponse $response): void
    {
        Log::info('Workspace created', [
            'workspace_id' => $response->workspaceId,
            'session_id' => $sessionId,
            'columns_count' => count($response->columns),
            'generations_count' => count($response->generations),
            'prompt_length' => mb_strlen($initialPrompt),
        ]);
    }

    private const MAX_PROMPT_CHARS = 100000;

    private function validatePromptLength(string $prompt): void
    {
        if (mb_strlen($prompt) > self::MAX_PROMPT_CHARS) {
            throw new \InvalidArgumentException(
                'Prompt exceeds maximum allowed length.'
            );
        }
    }
}
