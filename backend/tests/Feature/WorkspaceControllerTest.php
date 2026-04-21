<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creates_workspace_and_returns_dto(): void
    {
        $response = $this->postJson('/api/workspaces', [
            'initialPrompt' => 'Explain quantum computing',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'workspaceId',
            'columns' => [
                '*' => ['id', 'modelCode', 'position', 'status'],
            ],
            'generations' => [
                '*' => ['id', 'columnId', 'userMessageId', 'status'],
            ],
        ]);

        $this->assertCount(4, $response->json('columns'));
        $this->assertCount(4, $response->json('generations'));
    }

    #[Test]
    public function validation_rejects_empty_prompt(): void
    {
        $response = $this->postJson('/api/workspaces', [
            'initialPrompt' => '',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function validation_rejects_missing_prompt(): void
    {
        $response = $this->postJson('/api/workspaces', []);

        $response->assertUnprocessable();
    }

    #[Test]
    public function validation_rejects_prompt_exceeding_max_length(): void
    {
        $response = $this->postJson('/api/workspaces', [
            'initialPrompt' => str_repeat('a', 100001),
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function gets_workspace_by_id(): void
    {
        $createResponse = $this->postJson('/api/workspaces', [
            'initialPrompt' => 'Hello world',
        ]);

        $workspaceId = $createResponse->json('workspaceId');

        // Verify workspace data from the create response
        // (GET endpoint is covered by SessionScopingTest which tests session ownership)
        $createResponse->assertCreated();
        $this->assertNotNull($workspaceId);

        // Verify the workspace exists in the database and is bound to a session
        $workspace = \App\Models\Workspace::find($workspaceId);
        $this->assertNotNull($workspace);
        $this->assertNotNull($workspace->session_id);
        $this->assertCount(4, $workspace->columns);
    }

    #[Test]
    public function returns_404_for_nonexistent_workspace(): void
    {
        $response = $this->getJson('/api/workspaces/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    #[Test]
    public function generations_have_pending_status(): void
    {
        $response = $this->postJson('/api/workspaces', [
            'initialPrompt' => 'Hello world',
        ]);

        $generations = $response->json('generations');
        foreach ($generations as $gen) {
            $this->assertEquals('pending', $gen['status']);
        }
    }
}
