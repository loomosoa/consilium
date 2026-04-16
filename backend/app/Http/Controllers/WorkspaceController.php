<?php

namespace App\Http\Controllers;

use App\DTOs\WorkspaceResponse;
use App\Http\Requests\CreateWorkspaceRequest;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;

class WorkspaceController extends Controller
{
    public function __construct(
        private WorkspaceService $workspaceService,
    ) {}

    public function store(CreateWorkspaceRequest $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $initialPrompt = $request->validated('initialPrompt');

        $response = $this->workspaceService->create($sessionId, $initialPrompt);

        return response()->json($this->mapWorkspaceResponse($response), 201);
    }

    public function show(string $workspaceId): JsonResponse
    {
        $response = $this->workspaceService->find($workspaceId);

        if ($response === null) {
            return response()->json(['message' => 'Workspace not found'], 404);
        }

        return response()->json($this->mapWorkspaceResponse($response));
    }

    private function mapWorkspaceResponse(WorkspaceResponse $response): array
    {
        return [
            'workspaceId' => $response->workspaceId,
            'columns' => array_map(fn ($c) => [
                'id' => $c->id,
                'modelCode' => $c->modelCode,
                'position' => $c->position,
                'status' => $c->status,
            ], $response->columns),
            'generations' => array_map(fn ($g) => [
                'id' => $g->id,
                'columnId' => $g->columnId,
                'userMessageId' => $g->userMessageId,
                'status' => $g->status,
            ], $response->generations),
        ];
    }
}
