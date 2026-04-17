<?php

namespace Tests\Unit;

use App\DTOs\StreamCompleted;
use App\DTOs\StreamToken;
use App\DTOs\UpstreamError;
use App\Models\Message;
use App\Services\ApiKeyResolver;
use App\Services\ErrorMapper;
use App\Services\OpenRouterClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpenRouterClientTest extends TestCase
{
    use RefreshDatabase;

    private OpenRouterClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Подставляем API key через env
        config(['services.openrouter.key' => 'test-api-key']);

        $this->client = $this->app->make(OpenRouterClient::class);
    }

    #[Test]
    public function builds_correct_request_with_model_and_context(): void
    {
        $workspace = \App\Models\Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column = \App\Models\ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);

        Http::fake([
            'openrouter.ai/*' => Http::response(
                "data: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{\"content\":\"Hi\"},\"finish_reason\":null}]}\n\ndata: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":5,\"completion_tokens\":1}}\n\ndata: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $tokens = [];
        $completed = null;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [$msg],
            onToken: function (StreamToken $t) use (&$tokens) { $tokens[] = $t; },
            onCompleted: function (StreamCompleted $c) use (&$completed) { $completed = $c; },
            onError: fn (UpstreamError $e) => $this->fail('Should not error: '.$e->message),
            onCancel: fn () => $this->fail('Should not cancel'),
        );

        $this->assertCount(1, $tokens);
        $this->assertEquals('Hi', $tokens[0]->text);
        $this->assertEquals(1, $tokens[0]->sequence);
        $this->assertNotNull($completed);
        $this->assertEquals('stop', $completed->finishReason);
        $this->assertEquals(5, $completed->promptTokens);
        $this->assertEquals(1, $completed->completionTokens);
    }

    #[Test]
    public function parses_normal_token_stream(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(
                "data: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{\"content\":\"Hello\"},\"finish_reason\":null}]}\n\ndata: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{\"content\":\" world\"},\"finish_reason\":null}]}\n\ndata: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":3,\"completion_tokens\":2}}\n\ndata: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $tokens = [];
        $completed = null;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: function (StreamToken $t) use (&$tokens) { $tokens[] = $t; },
            onCompleted: function (StreamCompleted $c) use (&$completed) { $completed = $c; },
            onError: fn (UpstreamError $e) => $this->fail('Should not error'),
            onCancel: fn () => $this->fail('Should not cancel'),
        );

        $this->assertCount(2, $tokens);
        $this->assertEquals('Hello', $tokens[0]->text);
        $this->assertEquals(' world', $tokens[1]->text);
        $this->assertEquals(2, $completed->completionTokens);
    }

    #[Test]
    public function maps_429_rate_limit_to_retryable_error(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response('Rate limit exceeded', 429),
        ]);

        $error = null;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: fn () => $this->fail('Should not receive tokens'),
            onCompleted: fn () => $this->fail('Should not complete'),
            onError: function (UpstreamError $e) use (&$error) { $error = $e; },
            onCancel: fn () => $this->fail('Should not cancel'),
        );

        $this->assertNotNull($error);
        $this->assertEquals('rate_limit', $error->code);
        $this->assertTrue($error->retryable);
    }

    #[Test]
    public function maps_5xx_to_retryable_error(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response('Internal Server Error', 500),
        ]);

        $error = null;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: fn () => $this->fail('Should not receive tokens'),
            onCompleted: fn () => $this->fail('Should not complete'),
            onError: function (UpstreamError $e) use (&$error) { $error = $e; },
            onCancel: fn () => $this->fail('Should not cancel'),
        );

        $this->assertNotNull($error);
        $this->assertEquals('provider_unavailable', $error->code);
        $this->assertTrue($error->retryable);
    }

    #[Test]
    public function maps_timeout_to_retryable_error(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::failedConnection('Timeout'),
        ]);

        $error = null;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: fn () => $this->fail('Should not receive tokens'),
            onCompleted: fn () => $this->fail('Should not complete'),
            onError: function (UpstreamError $e) use (&$error) { $error = $e; },
            onCancel: fn () => $this->fail('Should not cancel'),
        );

        $this->assertNotNull($error);
        $this->assertEquals('connection_error', $error->code);
        $this->assertTrue($error->retryable);
    }

    #[Test]
    public function throws_exception_for_empty_model_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('openRouterModelId cannot be empty');

        $this->client->stream(
            openRouterModelId: '',
            messages: [],
            onToken: function () {},
            onCompleted: function () {},
            onError: function () {},
            onCancel: function () {},
        );
    }

    #[Test]
    public function cancels_stream_when_should_cancel_returns_true(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(
                "data: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{\"content\":\"Hello\"},\"finish_reason\":null}]}\n\ndata: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{\"content\":\" world\"},\"finish_reason\":null}]}\n\ndata: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $tokens = [];
        $cancelled = false;
        $callCount = 0;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: function (StreamToken $t) use (&$tokens) { $tokens[] = $t; },
            onCompleted: fn () => $this->fail('Should not complete'),
            onError: fn () => $this->fail('Should not error'),
            onCancel: function () use (&$cancelled) { $cancelled = true; },
            shouldCancel: function () use (&$callCount) {
                $callCount++;

                return $callCount >= 2;
            },
        );

        $this->assertTrue($cancelled);
        // Should have received at most 1 token before cancel
        $this->assertLessThanOrEqual(1, count($tokens));
    }
}
