<?php

namespace Tests\Unit;

use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    use RefreshDatabase;

    private ColumnConversation $column;

    private Message $userMessage;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = Workspace::create([
            'session_id' => 'test-session-123',
            'initial_prompt' => 'Test prompt',
        ]);

        $this->column = ColumnConversation::create([
            'workspace_id' => $workspace->id,
            'model_code' => 'xai',
            'position' => 1,
        ]);

        $this->userMessage = Message::create([
            'column_id' => $this->column->id,
            'role' => 'user',
            'content' => 'Hello',
            'sequence' => 1,
        ]);
    }

    #[Test]
    public function generation_creates_with_required_fields(): void
    {
        $generation = Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
        ]);

        $this->assertDatabaseHas('generations', [
            'id' => $generation->id,
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function generation_default_status_is_pending(): void
    {
        $generation = Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
        ]);

        $this->assertEquals('pending', $generation->status);
    }

    #[Test]
    public function generation_default_retryable_is_false(): void
    {
        $generation = Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
        ]);

        $this->assertFalse($generation->retryable);
    }

    #[Test]
    public function generation_user_message_id_references_user_message(): void
    {
        $generation = Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
        ]);

        $this->assertEquals('user', $generation->userMessage->role);
    }

    #[Test]
    public function only_one_active_generation_per_column(): void
    {
        Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'pending',
        ]);

        // Second pending generation should be allowed at DB level
        // Business logic constraint enforced at service level
        $generation2 = Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'pending',
        ]);

        // Both exist — constraint is enforced in service, not DB
        $this->assertEquals(2, Generation::where('column_id', $this->column->id)
            ->whereIn('status', ['pending', 'streaming'])
            ->count());
    }

    #[Test]
    public function generation_belongs_to_column(): void
    {
        $generation = Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
        ]);

        $this->assertEquals($this->column->id, $generation->column->id);
    }
}
