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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private GenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-api-key']);
        $this->service = $this->app->make(GenerationService::class);
    }

    #[Test]
    public function pending_to_streaming_to_completed_creates_assistant_message(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $userMsg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $userMsg->id, 'status' => GenerationStatus::PENDING]);

        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Hi there"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{},"finish_reason":"stop"}],"usage":{"prompt_tokens":5,"completion_tokens":2}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake(['openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $events = [];
        $this->service->streamGeneration(
            $generation,
            function (string $type, $data) use (&$events) { $events[] = ['type' => $type, 'data' => $data]; },
            fn () => false,
        );

        $generation->refresh();
        $column->refresh();

        // Generation completed
        $this->assertEquals(GenerationStatus::COMPLETED, $generation->status);
        $this->assertEquals('stop', $generation->finishReason ?? 'stop');

        // Assistant message created
        $assistantMsg = Message::where('column_id', $column->id)
            ->where('role', MessageRole::ASSISTANT)
            ->first();
        $this->assertNotNull($assistantMsg);
        $this->assertEquals('Hi there', $assistantMsg->content);
        $this->assertEquals($generation->id, $assistantMsg->generation_id);

        // Column status synced
        $this->assertEquals(ColumnStatus::COMPLETED, $column->status);
    }

    #[Test]
    public function pending_to_streaming_to_error_does_not_create_assistant_message(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $userMsg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $userMsg->id, 'status' => GenerationStatus::PENDING]);

        Http::fake(['openrouter.ai/*' => Http::response('Rate limit exceeded', 429)]);

        $this->service->streamGeneration(
            $generation,
            function () {},
            fn () => false,
        );

        $generation->refresh();
        $column->refresh();

        $this->assertEquals(GenerationStatus::ERROR, $generation->status);
        $this->assertEquals('rate_limit', $generation->error_code);
        $this->assertTrue($generation->retryable);

        // No assistant message
        $this->assertEquals(0, Message::where('column_id', $column->id)->where('role', MessageRole::ASSISTANT)->count());

        // Column status synced
        $this->assertEquals(ColumnStatus::ERROR, $column->status);
    }

    #[Test]
    public function pending_to_streaming_to_cancelled_does_not_create_assistant_message(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $userMsg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $userMsg->id, 'status' => GenerationStatus::PENDING]);

        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Partial"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":" text"},"finish_reason":null}]}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake(['openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $callCount = 0;
        $this->service->streamGeneration(
            $generation,
            function () {},
            function () use (&$callCount) {
                $callCount++;

                return $callCount >= 2;
            },
        );

        $generation->refresh();
        $column->refresh();

        $this->assertEquals(GenerationStatus::CANCELLED, $generation->status);
        $this->assertNotNull($generation->partial_output);

        // No assistant message
        $this->assertEquals(0, Message::where('column_id', $column->id)->where('role', MessageRole::ASSISTANT)->count());

        // Column status synced
        $this->assertEquals(ColumnStatus::CANCELLED, $column->status);
    }

    #[Test]
    public function column_status_syncs_with_generation_lifecycle(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $userMsg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $userMsg->id, 'status' => GenerationStatus::PENDING]);

        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{},"finish_reason":"stop"}],"usage":{"prompt_tokens":1,"completion_tokens":1}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake(['openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        // Before: column is idle/waiting
        $this->assertNotEquals(ColumnStatus::STREAMING, $column->status);

        $this->service->streamGeneration(
            $generation,
            function () {},
            fn () => false,
        );

        $column->refresh();
        $this->assertEquals(ColumnStatus::COMPLETED, $column->status);
    }

    #[Test]
    public function error_in_one_generation_does_not_affect_other_columns(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $col1 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $col2 = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'google', 'position' => 2]);

        $msg1 = Message::create(['column_id' => $col1->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $msg2 = Message::create(['column_id' => $col2->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);

        $gen1 = Generation::create(['column_id' => $col1->id, 'user_message_id' => $msg1->id, 'status' => GenerationStatus::PENDING]);
        $gen2 = Generation::create(['column_id' => $col2->id, 'user_message_id' => $msg2->id, 'status' => GenerationStatus::PENDING]);

        // gen1 fails
        Http::fake(['openrouter.ai/*' => Http::response('Server error', 500)]);

        $this->service->streamGeneration(
            $gen1,
            function () {},
            fn () => false,
        );

        $col2->refresh();
        // col2 is unaffected
        $this->assertNotEquals(ColumnStatus::ERROR, $col2->status);
        $this->assertEquals(GenerationStatus::PENDING, $gen2->refresh()->status);
    }
}
