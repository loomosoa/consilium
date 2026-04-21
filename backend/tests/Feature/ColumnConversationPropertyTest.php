<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\ColumnConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColumnConversationPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSessionOwnsWorkspace::class);
    }

    /**
     * Prop. 12: follow-up создаёт generation только для одной модели.
     */
    #[Test]
    public function follow_up_creates_generation_only_for_one_model(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column1 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $column2 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'arcee', 'position' => 2]);

        // Follow-up only to column1
        $response = $this->postJson("/api/columns/{$column1->id}/messages", [
            'prompt' => 'Follow up question',
        ]);

        $response->assertCreated();

        // Column1 should have a new generation
        $this->assertEquals(1, $column1->generations()->count());

        // Column2 should have no generations
        $this->assertEquals(0, $column2->generations()->count());
    }

    /**
     * Prop. 13: upstream payload содержит только историю данной колонки,
     * без сообщений из других.
     */
    #[Test]
    public function confirmed_history_isolated_per_column(): void
    {
        $service = $this->app->make(ColumnConversationService::class);

        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column1 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $column2 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'arcee', 'position' => 2]);

        // Add messages to column1
        $msg1 = Message::create(['column_id' => $column1->id, 'role' => 'user', 'content' => 'Col1 question', 'sequence' => 1]);
        $gen1 = Generation::create(['column_id' => $column1->id, 'user_message_id' => $msg1->id, 'status' => 'completed']);
        Message::create(['column_id' => $column1->id, 'role' => 'assistant', 'content' => 'Col1 answer', 'sequence' => 2, 'generation_id' => $gen1->id]);

        // Add messages to column2
        $msg2 = Message::create(['column_id' => $column2->id, 'role' => 'user', 'content' => 'Col2 question', 'sequence' => 1]);
        $gen2 = Generation::create(['column_id' => $column2->id, 'user_message_id' => $msg2->id, 'status' => 'completed']);
        Message::create(['column_id' => $column2->id, 'role' => 'assistant', 'content' => 'Col2 answer', 'sequence' => 2, 'generation_id' => $gen2->id]);

        // Verify isolation
        $history1 = $service->getConfirmedHistory($column1);
        $history2 = $service->getConfirmedHistory($column2);

        $contents1 = array_map(fn ($m) => $m->content, $history1);
        $contents2 = array_map(fn ($m) => $m->content, $history2);

        $this->assertContains('Col1 question', $contents1);
        $this->assertContains('Col1 answer', $contents1);
        $this->assertNotContains('Col2 question', $contents1);
        $this->assertNotContains('Col2 answer', $contents1);

        $this->assertContains('Col2 question', $contents2);
        $this->assertContains('Col2 answer', $contents2);
        $this->assertNotContains('Col1 question', $contents2);
        $this->assertNotContains('Col1 answer', $contents2);
    }

    /**
     * Prop. 13 variant: cancelled generation's output not in history.
     */
    #[Test]
    public function cancelled_generation_output_not_in_confirmed_history(): void
    {
        $service = $this->app->make(ColumnConversationService::class);

        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);

        $userMsg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $cancelledGen = Generation::create(['column_id' => $column->id, 'user_message_id' => $userMsg->id, 'status' => 'cancelled']);

        // Even if an assistant message was created (shouldn't be, but defensive check)
        Message::create(['column_id' => $column->id, 'role' => 'assistant', 'content' => 'Partial...', 'sequence' => 2, 'generation_id' => $cancelledGen->id]);

        $history = $service->getConfirmedHistory($column);

        $contents = array_map(fn ($m) => $m->content, $history);
        $this->assertNotContains('Partial...', $contents);
        $this->assertCount(1, $history);
    }
}
