<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColumnMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private ColumnConversation $column;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable session ownership middleware for this test class
        // (session scoping is tested separately in SessionScopingTest and EnsureSessionOwnsWorkspaceTest)
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSessionOwnsWorkspace::class);

        $this->workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $this->column = ColumnConversation::create([
            'workspace_id' => $this->workspace->id,
            'model_code' => 'nvidia',
            'position' => 1,
        ]);
    }

    #[Test]
    public function follow_up_creates_generation_for_specified_column(): void
    {
        $response = $this->postJson("/api/columns/{$this->column->id}/messages", [
            'prompt' => 'Follow up question',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'columnId',
            'generation' => ['id', 'columnId', 'userMessageId', 'status'],
        ]);

        $this->assertEquals($this->column->id, $response->json('columnId'));
        $this->assertEquals('pending', $response->json('generation.status'));
    }

    #[Test]
    public function follow_up_creates_user_message_with_correct_sequence(): void
    {
        // Existing message with sequence 1
        Message::create([
            'column_id' => $this->column->id,
            'role' => 'user',
            'content' => 'First message',
            'sequence' => 1,
        ]);

        $this->postJson("/api/columns/{$this->column->id}/messages", [
            'prompt' => 'Second message',
        ]);

        $this->assertDatabaseHas('messages', [
            'column_id' => $this->column->id,
            'role' => 'user',
            'content' => 'Second message',
            'sequence' => 2,
        ]);
    }

    #[Test]
    public function follow_up_rejected_when_active_generation_exists(): void
    {
        $userMsg = Message::create([
            'column_id' => $this->column->id,
            'role' => 'user',
            'content' => 'First',
            'sequence' => 1,
        ]);

        Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $userMsg->id,
            'status' => GenerationStatus::PENDING,
        ]);

        $response = $this->postJson("/api/columns/{$this->column->id}/messages", [
            'prompt' => 'Follow up',
        ]);

        $response->assertStatus(409);
    }

    #[Test]
    public function follow_up_allowed_after_generation_completed(): void
    {
        $userMsg = Message::create([
            'column_id' => $this->column->id,
            'role' => 'user',
            'content' => 'First',
            'sequence' => 1,
        ]);

        Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $userMsg->id,
            'status' => GenerationStatus::COMPLETED,
        ]);

        $response = $this->postJson("/api/columns/{$this->column->id}/messages", [
            'prompt' => 'Follow up',
        ]);

        $response->assertCreated();
    }

    #[Test]
    public function validation_rejects_empty_prompt(): void
    {
        $response = $this->postJson("/api/columns/{$this->column->id}/messages", [
            'prompt' => '',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function returns_404_for_nonexistent_column(): void
    {
        $response = $this->postJson('/api/columns/00000000-0000-0000-0000-000000000000/messages', [
            'prompt' => 'Hello',
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function follow_up_updates_column_status_to_waiting(): void
    {
        $this->postJson("/api/columns/{$this->column->id}/messages", [
            'prompt' => 'Follow up',
        ]);

        $this->column->refresh();
        $this->assertEquals('waiting', $this->column->status->value);
    }
}
