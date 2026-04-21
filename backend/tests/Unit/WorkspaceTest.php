<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\WorkspaceState;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workspace_creates_with_required_fields(): void
    {
        $workspace = Workspace::create([
            'session_id' => 'test-session-123',
            'state' => 'initializing',
            'initial_prompt' => 'Hello world',
        ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'session_id' => 'test-session-123',
            'state' => 'initializing',
        ]);
        $this->assertEquals('Hello world', $workspace->initial_prompt);
        $this->assertNotNull($workspace->id);
    }

    #[Test]
    public function workspace_initial_prompt_can_be_long(): void
    {
        $longPrompt = str_repeat('a', 100000);

        $workspace = Workspace::create([
            'session_id' => 'test-session-123',
            'initial_prompt' => $longPrompt,
        ]);

        $this->assertEquals($longPrompt, $workspace->initial_prompt);
    }

    #[Test]
    public function workspace_has_uuid_primary_key(): void
    {
        $workspace = Workspace::create([
            'session_id' => 'test-session-123',
            'initial_prompt' => 'Test prompt',
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $workspace->id,
        );
    }

    #[Test]
    public function workspace_default_state_is_initializing(): void
    {
        $workspace = Workspace::create([
            'session_id' => 'test-session-123',
            'initial_prompt' => 'Test prompt',
        ]);

        $this->assertEquals(WorkspaceState::INITIALIZING, $workspace->state);
    }
}
