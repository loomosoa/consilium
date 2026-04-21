<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ColumnStatus;
use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\GenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CancelPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-api-key']);
    }

    /**
     * Prop. 22: нажатие «Стоп» прекращает стриминг, прерывает upstream, переводит generation в cancelled.
     */
    #[Test]
    public function cancel_stops_streaming_and_transitions_to_cancelled(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::STREAMING]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::STREAMING,
            'partial_output' => 'Partial text',
            'started_at' => now(),
        ]);

        $service = $this->app->make(GenerationService::class);
        $service->cancelGeneration($generation);

        $generation->refresh();
        $this->assertEquals(GenerationStatus::CANCELLED, $generation->status);
        $this->assertEquals(ColumnStatus::CANCELLED, $column->refresh()->status);
        $this->assertNotNull($generation->completed_at);
    }

    /**
     * Prop. 22: cancel прерывает upstream — shouldCancel callback возвращает true.
     */
    #[Test]
    public function cancel_interrupts_upstream_via_should_cancel(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $msg->id, 'status' => GenerationStatus::PENDING]);

        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Token1"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Token2"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Token3"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{},"finish_reason":"stop"}],"usage":{"prompt_tokens":1,"completion_tokens":3}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake(['openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $cancelled = false;
        $tokenCount = 0;

        // Simulate cancel after first token
        $shouldCancel = function () use (&$tokenCount, &$cancelled) {
            if ($tokenCount >= 1 && ! $cancelled) {
                $cancelled = true;

                return true;
            }

            return false;
        };

        $service = $this->app->make(GenerationService::class);
        $service->streamGeneration(
            $generation,
            function (string $type) use (&$tokenCount) {
                if ($type === 'token') {
                    $tokenCount++;
                }
            },
            $shouldCancel,
        );

        $generation->refresh();
        $this->assertTrue($cancelled);
        $this->assertEquals(GenerationStatus::CANCELLED, $generation->status);
        $this->assertLessThan(3, $tokenCount); // Not all tokens received
    }

    /**
     * Prop. 25: cancelled generation — partialOutput сохранён, но не включён в контекст.
     */
    #[Test]
    public function cancelled_generation_partial_output_saved_not_in_context(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::STREAMING]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::STREAMING,
            'partial_output' => 'This is partial output from cancelled generation',
            'started_at' => now(),
        ]);

        $service = $this->app->make(GenerationService::class);
        $service->cancelGeneration($generation);

        // partialOutput is saved
        $generation->refresh();
        $this->assertEquals('This is partial output from cancelled generation', $generation->partial_output);
        $this->assertEquals(GenerationStatus::CANCELLED, $generation->status);

        // No assistant message created — partialOutput not in context
        $assistantMessages = Message::where('column_id', $column->id)
            ->where('role', MessageRole::ASSISTANT)
            ->count();
        $this->assertEquals(0, $assistantMessages);

        // Confirmed history does not include partial output
        $conversationService = $this->app->make(\App\Services\ColumnConversationService::class);
        $history = $conversationService->getConfirmedHistory($column);
        $historyText = implode('', array_map(fn (Message $m) => $m->content, $history));
        $this->assertStringNotContainsString('This is partial output from cancelled generation', $historyText);
    }
}
