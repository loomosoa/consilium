<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\StreamCompleted;
use App\DTOs\StreamToken;
use App\DTOs\UpstreamError;
use App\Enums\ColumnStatus;
use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\GenerationService;
use App\Services\SseEventFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerationPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-api-key']);
    }

    /**
     * Prop. 06: каждый SSE-event token дописывается только в соответствующую колонку.
     */
    #[Test]
    public function token_events_written_only_to_corresponding_column(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $col1 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $col2 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'google', 'position' => 2]);

        $msg1 = Message::create(['column_id' => $col1->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $msg2 = Message::create(['column_id' => $col2->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);

        $gen1 = Generation::create(['column_id' => $col1->id, 'user_message_id' => $msg1->id, 'status' => GenerationStatus::PENDING]);
        $gen2 = Generation::create(['column_id' => $col2->id, 'user_message_id' => $msg2->id, 'status' => GenerationStatus::PENDING]);

        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Nvidia response"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{},"finish_reason":"stop"}],"usage":{"prompt_tokens":1,"completion_tokens":1}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake(['openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $service = $this->app->make(GenerationService::class);
        $service->streamGeneration($gen1, function () {}, fn () => false);

        // gen1 completed → assistant message in col1 only
        $col1Messages = Message::where('column_id', $col1->id)->where('role', MessageRole::ASSISTANT)->count();
        $col2Messages = Message::where('column_id', $col2->id)->where('role', MessageRole::ASSISTANT)->count();

        $this->assertEquals(1, $col1Messages);
        $this->assertEquals(0, $col2Messages);

        // gen2 still pending — no assistant message
        $this->assertEquals(GenerationStatus::PENDING, $gen2->refresh()->status);
    }

    /**
     * Prop. 10: ошибка одной generation не меняет статус других колонок.
     */
    #[Test]
    public function error_in_one_generation_does_not_affect_other_columns(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $col1 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $col2 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'google', 'position' => 2]);
        $col3 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'meta', 'position' => 3]);

        $msg1 = Message::create(['column_id' => $col1->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $msg2 = Message::create(['column_id' => $col2->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $msg3 = Message::create(['column_id' => $col3->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);

        $gen1 = Generation::create(['column_id' => $col1->id, 'user_message_id' => $msg1->id, 'status' => GenerationStatus::PENDING]);
        $gen2 = Generation::create(['column_id' => $col2->id, 'user_message_id' => $msg2->id, 'status' => GenerationStatus::PENDING]);
        $gen3 = Generation::create(['column_id' => $col3->id, 'user_message_id' => $msg3->id, 'status' => GenerationStatus::PENDING]);

        // gen1 fails with 429
        Http::fake(['openrouter.ai/*' => Http::response('Rate limit', 429)]);

        $service = $this->app->make(GenerationService::class);
        $service->streamGeneration($gen1, function () {}, fn () => false);

        // col1 is in error
        $this->assertEquals(ColumnStatus::ERROR, $col1->refresh()->status);
        $this->assertEquals(GenerationStatus::ERROR, $gen1->refresh()->status);

        // col2 and col3 are unaffected
        $this->assertNotEquals(ColumnStatus::ERROR, $col2->refresh()->status);
        $this->assertNotEquals(ColumnStatus::ERROR, $col3->refresh()->status);
        $this->assertEquals(GenerationStatus::PENDING, $gen2->refresh()->status);
        $this->assertEquals(GenerationStatus::PENDING, $gen3->refresh()->status);
    }

    /**
     * Prop. 18: generation отображается через SSE.
     */
    #[Test]
    public function generation_delivered_via_sse_events(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $msg->id, 'status' => GenerationStatus::PENDING]);

        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Hello"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":" world"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{},"finish_reason":"stop"}],"usage":{"prompt_tokens":3,"completion_tokens":2}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake(['openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $sseFactory = $this->app->make(SseEventFactory::class);
        $events = [];

        $sendSse = function (string $type, $data) use ($sseFactory, $generation, &$events) {
            $event = match ($type) {
                'token' => $sseFactory->token($data),
                'completed' => $sseFactory->completed($generation->id, $data['assistantMessageId'], $data['dto']),
                'error' => $sseFactory->error($generation->id, $data),
                'cancelled' => $sseFactory->cancelled($generation->id, $data['partialOutput'] ?? null),
                default => '',
            };
            $events[] = ['type' => $type, 'raw' => $event];
        };

        $service = $this->app->make(GenerationService::class);
        $service->streamGeneration($generation, $sendSse, fn () => false);

        // SSE events: 2 tokens + 1 completed
        $this->assertCount(3, $events);

        // All events have proper SSE format
        foreach ($events as $event) {
            $this->assertStringStartsWith('event: ', $event['raw']);
            $this->assertStringContainsString('data: ', $event['raw']);
            $this->assertStringEndsWith("\n\n", $event['raw']);
        }

        // Token events contain text
        $this->assertEquals('token', $events[0]['type']);
        $this->assertStringContainsString('"text":"Hello"', $events[0]['raw']);
        $this->assertEquals('token', $events[1]['type']);
        $this->assertStringContainsString('"text":" world"', $events[1]['raw']);

        // Completed event contains generationId and finishReason
        $this->assertEquals('completed', $events[2]['type']);
        $this->assertStringContainsString('"generationId"', $events[2]['raw']);
        $this->assertStringContainsString('"finishReason":"stop"', $events[2]['raw']);
    }
}
