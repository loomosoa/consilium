<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ColumnStatus;
use App\Enums\GenerationStatus;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\GenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RetryPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-api-key']);
    }

    /**
     * Prop. 09: error generation — retryable flag set, retry creates new generation.
     */
    #[Test]
    public function error_generation_is_retryable_and_retry_creates_new_generation(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::ERROR]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);

        // Simulate error generation
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::PENDING,
        ]);

        // Stream with error response
        Http::fake(['openrouter.ai/*' => Http::response('Rate limit', 429)]);

        $service = $this->app->make(GenerationService::class);
        $service->streamGeneration($generation, function () {}, fn () => false);

        // Generation should be in error state with retryable flag
        $generation->refresh();
        $this->assertEquals(GenerationStatus::ERROR, $generation->status);
        $this->assertTrue($generation->retryable);

        // Retry should create new generation
        $newGeneration = $service->retryGeneration($generation);
        $this->assertEquals(GenerationStatus::PENDING, $newGeneration->status);
        $this->assertEquals($generation->user_message_id, $newGeneration->user_message_id);
        $this->assertNotEquals($generation->id, $newGeneration->id);
    }

    /**
     * Prop. 10: error in one column does not affect other columns.
     */
    #[Test]
    public function error_in_one_column_does_not_affect_retry_of_other_columns(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $col1 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::ERROR]);
        $col2 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'google', 'position' => 2, 'status' => ColumnStatus::COMPLETED]);
        $col3 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'meta', 'position' => 3, 'status' => ColumnStatus::ERROR]);

        $msg1 = Message::create(['column_id' => $col1->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $msg2 = Message::create(['column_id' => $col2->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $msg3 = Message::create(['column_id' => $col3->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);

        $gen1 = Generation::create(['column_id' => $col1->id, 'user_message_id' => $msg1->id, 'status' => GenerationStatus::ERROR, 'retryable' => true]);
        $gen2 = Generation::create(['column_id' => $col2->id, 'user_message_id' => $msg2->id, 'status' => GenerationStatus::COMPLETED]);
        $gen3 = Generation::create(['column_id' => $col3->id, 'user_message_id' => $msg3->id, 'status' => GenerationStatus::ERROR, 'retryable' => true]);

        $service = $this->app->make(GenerationService::class);

        // Retry col1 — should succeed
        $newGen1 = $service->retryGeneration($gen1);
        $this->assertEquals(GenerationStatus::PENDING, $newGen1->status);
        $this->assertEquals(ColumnStatus::WAITING, $col1->refresh()->status);

        // col2 should remain completed
        $this->assertEquals(GenerationStatus::COMPLETED, $gen2->refresh()->status);
        $this->assertEquals(ColumnStatus::COMPLETED, $col2->refresh()->status);

        // Retry col3 — should also succeed independently
        $newGen3 = $service->retryGeneration($gen3);
        $this->assertEquals(GenerationStatus::PENDING, $newGen3->status);
        $this->assertEquals(ColumnStatus::WAITING, $col3->refresh()->status);

        // col1 should still be waiting (not affected by col3 retry)
        $this->assertEquals(ColumnStatus::WAITING, $col1->refresh()->status);
    }

    /**
     * Prop. 09 (extended): retry uses only confirmed history, partialOutput excluded.
     */
    #[Test]
    public function retry_uses_only_confirmed_history_partial_output_excluded(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::ERROR]);

        // User message
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);

        // Failed generation with partial output
        $failedGen = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::ERROR,
            'partial_output' => 'Incomplete response that should NOT be in context',
            'retryable' => true,
        ]);

        $service = $this->app->make(GenerationService::class);
        $newGeneration = $service->retryGeneration($failedGen);

        // New generation has no partial_output
        $this->assertNull($newGeneration->partial_output);

        // Confirmed history does not include partial output
        $conversationService = $this->app->make(\App\Services\ColumnConversationService::class);
        $history = $conversationService->getConfirmedHistory($column);

        // Only the user message should be in confirmed history
        $this->assertCount(1, $history);
        $this->assertEquals('Hello', $history[0]->content);
    }
}
