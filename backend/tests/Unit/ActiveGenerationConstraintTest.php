<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActiveGenerationConstraintTest extends TestCase
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
    public function only_one_pending_generation_per_column_enforced_by_db(): void
    {
        Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'pending',
        ]);

        $this->expectException(QueryException::class);

        Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function only_one_streaming_generation_per_column_enforced_by_db(): void
    {
        Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'streaming',
        ]);

        $this->expectException(QueryException::class);

        Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'streaming',
        ]);
    }

    #[Test]
    public function multiple_completed_generations_allowed_per_column(): void
    {
        Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'completed',
        ]);

        Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'completed',
        ]);

        $this->assertEquals(2, Generation::where('column_id', $this->column->id)
            ->where('status', 'completed')
            ->count());
    }

    #[Test]
    public function pending_and_completed_generations_can_coexist(): void
    {
        Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'completed',
        ]);

        $pending = Generation::create([
            'column_id' => $this->column->id,
            'user_message_id' => $this->userMessage->id,
            'status' => 'pending',
        ]);

        $this->assertNotNull($pending);
        $this->assertEquals(2, Generation::where('column_id', $this->column->id)->count());
    }
}
