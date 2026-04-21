<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerationStreamControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-api-key']);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSessionOwnsWorkspace::class);
    }

    #[Test]
    public function stream_returns_sse_content_type(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $msg->id, 'status' => GenerationStatus::PENDING]);

        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{},"finish_reason":"stop"}],"usage":{"prompt_tokens":1,"completion_tokens":1}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake(['openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $response = $this->get("/api/generations/{$generation->id}/stream");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    }

    #[Test]
    public function stream_returns_correct_sse_events(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $msg->id, 'status' => GenerationStatus::PENDING]);

        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Hi"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{},"finish_reason":"stop"}],"usage":{"prompt_tokens":5,"completion_tokens":1}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake(['openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        // Capture SSE events via GenerationService + SseEventFactory (unit-level)
        $sseFactory = app(\App\Services\SseEventFactory::class);
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

        $service = app(\App\Services\GenerationService::class);
        $service->streamGeneration($generation, $sendSse, fn () => false);

        // Verify event sequence
        $this->assertCount(2, $events); // token + completed
        $this->assertEquals('token', $events[0]['type']);
        $this->assertStringContainsString('event: token', $events[0]['raw']);
        $this->assertEquals('completed', $events[1]['type']);
        $this->assertStringContainsString('event: completed', $events[1]['raw']);
    }

    #[Test]
    public function stream_returns_404_for_nonexistent_generation(): void
    {
        $response = $this->get('/api/generations/nonexistent-id/stream');

        $response->assertStatus(404);
    }

    #[Test]
    public function stream_returns_422_for_non_active_generation(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $msg->id, 'status' => GenerationStatus::COMPLETED]);

        $response = $this->get("/api/generations/{$generation->id}/stream");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Generation is not active']);
    }
}
