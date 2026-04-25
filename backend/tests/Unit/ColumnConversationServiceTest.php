<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\MessageRole;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\ColumnConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColumnConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ColumnConversationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ColumnConversationService::class);
    }

    #[Test]
    public function history_contains_only_confirmed_messages_of_given_column(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column1 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $column2 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'llama', 'position' => 2]);

        Message::create(['column_id' => $column1->id, 'role' => 'user', 'content' => 'Hello from col1', 'sequence' => 1]);
        Message::create(['column_id' => $column2->id, 'role' => 'user', 'content' => 'Hello from col2', 'sequence' => 1]);

        $history = $this->service->getConfirmedHistory($column1);

        $this->assertCount(1, $history);
        $this->assertEquals('Hello from col1', $history[0]->content);
    }

    #[Test]
    public function history_excludes_partial_output_from_cancelled_generation(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);

        $userMsg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $gen = Generation::create(['column_id' => $column->id, 'user_message_id' => $userMsg->id, 'status' => 'cancelled']);

        // Cancelled generation produces no assistant message
        // So history should only contain the user message
        $history = $this->service->getConfirmedHistory($column);

        $this->assertCount(1, $history);
        $this->assertEquals(MessageRole::USER, $history[0]->role);
    }

    #[Test]
    public function history_excludes_partial_output_from_error_generation(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);

        $userMsg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        Generation::create(['column_id' => $column->id, 'user_message_id' => $userMsg->id, 'status' => 'error']);

        $history = $this->service->getConfirmedHistory($column);

        $this->assertCount(1, $history);
        $this->assertEquals(MessageRole::USER, $history[0]->role);
    }

    #[Test]
    public function history_includes_assistant_message_from_completed_generation(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);

        $userMsg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $gen = Generation::create(['column_id' => $column->id, 'user_message_id' => $userMsg->id, 'status' => 'completed']);

        Message::create([
            'column_id' => $column->id,
            'role' => 'assistant',
            'content' => 'Hi there!',
            'sequence' => 2,
            'generation_id' => $gen->id,
        ]);

        $history = $this->service->getConfirmedHistory($column);

        $this->assertCount(2, $history);
        $this->assertEquals(MessageRole::USER, $history[0]->role);
        $this->assertEquals(MessageRole::ASSISTANT, $history[1]->role);
        $this->assertEquals('Hi there!', $history[1]->content);
    }

    #[Test]
    public function history_excludes_assistant_message_from_non_completed_generation(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);

        $userMsg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $gen = Generation::create(['column_id' => $column->id, 'user_message_id' => $userMsg->id, 'status' => 'streaming']);

        // Assistant message exists but generation is not completed
        Message::create([
            'column_id' => $column->id,
            'role' => 'assistant',
            'content' => 'Partial...',
            'sequence' => 2,
            'generation_id' => $gen->id,
        ]);

        $history = $this->service->getConfirmedHistory($column);

        $this->assertCount(1, $history);
        $this->assertEquals(MessageRole::USER, $history[0]->role);
    }

    #[Test]
    public function old_messages_are_trimmed_when_exceeding_context_window(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'openai-free', 'position' => 1]);

        // openai-free contextWindow = 8192 tokens, *2 = 16384 chars
        // Create messages that exceed this
        $longContent = str_repeat('a', 10000);

        for ($i = 1; $i <= 3; $i++) {
            Message::create([
                'column_id' => $column->id,
                'role' => 'user',
                'content' => $longContent,
                'sequence' => $i,
            ]);
        }

        // 3 * 10000 = 30000 > 16384, should trim
        $history = $this->service->getConfirmedHistory($column);

        // After trimming, only the newest messages should remain
        $totalChars = array_sum(array_map(fn ($m) => mb_strlen($m->content), $history));
        $this->assertLessThanOrEqual(16384, $totalChars);
        $this->assertGreaterThan(0, count($history));
    }

    #[Test]
    public function trimming_throws_exception_when_all_messages_exceed_context(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Context window too small for any message.');

        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'openai-free', 'position' => 1]);

        // openai-free contextWindow = 8192 * 2 = 16384 chars
        // Create a message that exceeds this
        $longContent = str_repeat('b', 20000);
        Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => $longContent, 'sequence' => 1]);

        $this->service->getConfirmedHistory($column);
    }

    #[Test]
    public function no_cross_column_contamination(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column1 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $column2 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'llama', 'position' => 2]);

        Message::create(['column_id' => $column1->id, 'role' => 'user', 'content' => 'Col1 msg', 'sequence' => 1]);
        Message::create(['column_id' => $column2->id, 'role' => 'user', 'content' => 'Col2 msg', 'sequence' => 1]);

        $history1 = $this->service->getConfirmedHistory($column1);
        $history2 = $this->service->getConfirmedHistory($column2);

        $this->assertCount(1, $history1);
        $this->assertEquals('Col1 msg', $history1[0]->content);

        $this->assertCount(1, $history2);
        $this->assertEquals('Col2 msg', $history2[0]->content);
    }
}
