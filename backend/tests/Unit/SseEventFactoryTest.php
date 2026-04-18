<?php

namespace Tests\Unit;

use App\DTOs\StreamCompleted;
use App\DTOs\StreamToken;
use App\DTOs\UpstreamError;
use App\Enums\GenerationStatus;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\SseEventFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SseEventFactoryTest extends TestCase
{
    private SseEventFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new SseEventFactory;
    }

    #[Test]
    public function meta_event_format(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $msg->id, 'status' => GenerationStatus::PENDING]);

        $result = $this->factory->meta($generation);

        $this->assertStringStartsWith('event: meta', $result);
        $this->assertStringContainsString('"generationId"', $result);
        $this->assertStringContainsString('"columnId"', $result);
        $this->assertStringContainsString('"modelCode":"nvidia"', $result);
        $this->assertStringContainsString('"status":"pending"', $result);
        $this->assertStringEndsWith("\n\n", $result);
    }

    #[Test]
    public function token_event_format(): void
    {
        $token = new StreamToken(text: 'Hello', sequence: 1);
        $result = $this->factory->token($token);

        $this->assertStringStartsWith('event: token', $result);
        $this->assertStringContainsString('"text":"Hello"', $result);
        $this->assertStringContainsString('"sequence":1', $result);
        $this->assertStringEndsWith("\n\n", $result);
    }

    #[Test]
    public function completed_event_format(): void
    {
        $completed = new StreamCompleted(finishReason: 'stop', promptTokens: 10, completionTokens: 5);
        $result = $this->factory->completed('gen-123', 'msg-456', $completed);

        $this->assertStringStartsWith('event: completed', $result);
        $this->assertStringContainsString('"generationId":"gen-123"', $result);
        $this->assertStringContainsString('"assistantMessageId":"msg-456"', $result);
        $this->assertStringContainsString('"finishReason":"stop"', $result);
        $this->assertStringContainsString('"promptTokens":10', $result);
        $this->assertStringContainsString('"completionTokens":5', $result);
        $this->assertStringEndsWith("\n\n", $result);
    }

    #[Test]
    public function error_event_format(): void
    {
        $error = new UpstreamError(code: 'rate_limit', message: 'Rate limit exceeded', retryable: true);
        $result = $this->factory->error('gen-123', $error);

        $this->assertStringStartsWith('event: error', $result);
        $this->assertStringContainsString('"generationId":"gen-123"', $result);
        $this->assertStringContainsString('"code":"rate_limit"', $result);
        $this->assertStringContainsString('"retryable":true', $result);
        $this->assertStringEndsWith("\n\n", $result);
    }

    #[Test]
    public function cancelled_event_format(): void
    {
        $result = $this->factory->cancelled('gen-123', 'Partial output');

        $this->assertStringStartsWith('event: cancelled', $result);
        $this->assertStringContainsString('"generationId":"gen-123"', $result);
        $this->assertStringContainsString('"partialOutput":"Partial output"', $result);
        $this->assertStringEndsWith("\n\n", $result);
    }

    #[Test]
    public function heartbeat_event_format(): void
    {
        $result = $this->factory->heartbeat();

        $this->assertStringStartsWith('event: heartbeat', $result);
        $this->assertStringContainsString('"timestamp"', $result);
        $this->assertStringEndsWith("\n\n", $result);
    }
}
