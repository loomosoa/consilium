<?php

namespace Tests\Unit;

use App\Models\ColumnConversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private ColumnConversation $column;

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
    }

    #[Test]
    public function message_creates_with_required_fields(): void
    {
        $message = Message::create([
            'column_id' => $this->column->id,
            'role' => 'user',
            'content' => 'Hello world',
            'sequence' => 1,
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'column_id' => $this->column->id,
            'role' => 'user',
            'sequence' => 1,
        ]);
    }

    #[Test]
    public function message_sequence_is_unique_within_column(): void
    {
        Message::create([
            'column_id' => $this->column->id,
            'role' => 'user',
            'content' => 'First',
            'sequence' => 1,
        ]);

        $this->expectException(QueryException::class);

        Message::create([
            'column_id' => $this->column->id,
            'role' => 'assistant',
            'content' => 'Second',
            'sequence' => 1,
        ]);
    }

    #[Test]
    public function message_role_belongs_to_enum(): void
    {
        foreach (['user', 'assistant', 'system'] as $role) {
            $message = Message::create([
                'column_id' => $this->column->id,
                'role' => $role,
                'content' => "Message as $role",
                'sequence' => Message::where('column_id', $this->column->id)->max('sequence') + 1 ?? 1,
            ]);
            $this->assertEquals($role, $message->role);
        }
    }

    #[Test]
    public function message_belongs_to_column(): void
    {
        $message = Message::create([
            'column_id' => $this->column->id,
            'role' => 'user',
            'content' => 'Hello',
            'sequence' => 1,
        ]);

        $this->assertEquals($this->column->id, $message->column->id);
    }
}
