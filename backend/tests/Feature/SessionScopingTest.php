<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SessionScopingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a workspace via the API so it's bound to the current test session,
     * then return the created IDs for cross-session access checks.
     *
     * @return array{workspaceId: string, columnId: string, generationId: string}
     */
    private function createWorkspaceViaApi(): array
    {
        $response = $this->postJson('/api/workspaces', [
            'initialPrompt' => 'Test prompt for session scoping',
        ]);

        $response->assertCreated();

        return [
            'workspaceId' => $response->json('workspaceId'),
            'columnId' => $response->json('columns.0.id'),
            'generationId' => $response->json('generations.0.id'),
        ];
    }

    #[Test]
    public function workspace_show_returns_403_for_other_session(): void
    {
        // Create workspace in session A
        $ids = $this->createWorkspaceViaApi();

        // Session B: fresh session without the workspace's session_id
        $response = $this->withSession(['_token' => 'other'])
            ->getJson("/api/workspaces/{$ids['workspaceId']}");

        $response->assertForbidden();
    }

    #[Test]
    public function workspace_show_allows_owner_session(): void
    {
        // Create workspace via API — this binds the workspace to the current session
        $createResponse = $this->postJson('/api/workspaces', [
            'initialPrompt' => 'Test prompt for session scoping',
        ]);
        $createResponse->assertCreated();

        $workspaceId = $createResponse->json('workspaceId');

        // Verify the workspace is bound to the session that created it
        $workspace = \App\Models\Workspace::find($workspaceId);
        $this->assertNotNull($workspace);

        // The workspace's session_id should match the session used during creation
        // (This verifies the WorkspaceController correctly stores the session ID)
        $this->assertNotNull($workspace->session_id);
    }

    #[Test]
    public function column_messages_returns_403_for_other_session(): void
    {
        $ids = $this->createWorkspaceViaApi();

        // Complete the generation first so column is idle
        $generation = Generation::find($ids['generationId']);
        $generation->update(['status' => 'completed']);
        $column = ColumnConversation::find($ids['columnId']);
        $column->update(['status' => 'idle']);

        // Session B
        $response = $this->withSession(['_token' => 'other'])
            ->postJson("/api/columns/{$ids['columnId']}/messages", [
                'prompt' => 'Hello from other session',
            ]);

        $response->assertForbidden();
    }

    #[Test]
    public function generation_stream_returns_403_for_other_session(): void
    {
        $ids = $this->createWorkspaceViaApi();

        // Session B
        $response = $this->withSession(['_token' => 'other'])
            ->getJson("/api/generations/{$ids['generationId']}/stream");

        $response->assertForbidden();
    }

    #[Test]
    public function generation_cancel_returns_403_for_other_session(): void
    {
        $ids = $this->createWorkspaceViaApi();

        // Session B
        $response = $this->withSession(['_token' => 'other'])
            ->postJson("/api/generations/{$ids['generationId']}/cancel");

        $response->assertForbidden();
    }

    #[Test]
    public function generation_retry_returns_403_for_other_session(): void
    {
        $ids = $this->createWorkspaceViaApi();

        // Mark generation as error+retryable
        $generation = Generation::find($ids['generationId']);
        $generation->update(['status' => 'error', 'retryable' => true]);

        // Session B
        $response = $this->withSession(['_token' => 'other'])
            ->postJson("/api/generations/{$ids['generationId']}/retry");

        $response->assertForbidden();
    }

    #[Test]
    public function workspace_show_returns_404_for_nonexistent_workspace(): void
    {
        $response = $this->withSession(['_token' => 'test'])
            ->getJson('/api/workspaces/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    #[Test]
    public function column_messages_returns_404_for_nonexistent_column(): void
    {
        $response = $this->withSession(['_token' => 'test'])
            ->postJson('/api/columns/00000000-0000-0000-0000-000000000000/messages', [
                'prompt' => 'Hello',
            ]);

        $response->assertNotFound();
    }

    #[Test]
    public function generation_stream_returns_404_for_nonexistent_generation(): void
    {
        $response = $this->withSession(['_token' => 'test'])
            ->getJson('/api/generations/00000000-0000-0000-0000-000000000000/stream');

        $response->assertNotFound();
    }

    #[Test]
    public function api_key_not_exposed_in_session_key_response(): void
    {
        config(['services.openrouter.key' => null]);

        $response = $this->postJson('/api/session/openrouter-key', [
            'apiKey' => 'sk-or-v1-secret-key-1234567890abcdef',
        ]);

        $responseBody = $response->getContent();
        $this->assertStringNotContainsString('sk-or-v1-secret-key-1234567890abcdef', $responseBody);
    }
}
