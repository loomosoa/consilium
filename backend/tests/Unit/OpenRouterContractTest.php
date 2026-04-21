<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\StreamCompleted;
use App\DTOs\StreamToken;
use App\DTOs\UpstreamError;
use App\Services\OpenRouterClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Contract-тесты: проверка поведения OpenRouterClient
 * на различных фикстурах ответов OpenRouter API.
 */
class OpenRouterContractTest extends TestCase
{
    use RefreshDatabase;

    private OpenRouterClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-api-key']);
        $this->client = $this->app->make(OpenRouterClient::class);
    }

    /**
     * Фикстура: нормальный поток токенов.
     */
    #[Test]
    public function normal_token_stream(): void
    {
        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"role":"assistant","content":"Hello"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":" world"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"!"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{},"finish_reason":"stop"}],"usage":{"prompt_tokens":10,"completion_tokens":5}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake([
            'openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $tokens = [];
        $completed = null;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: function (StreamToken $t) use (&$tokens) { $tokens[] = $t; },
            onCompleted: function (StreamCompleted $c) use (&$completed) { $completed = $c; },
            onError: function (UpstreamError $e) { $this->fail('Should not error: '.$e->message); },
            onCancel: function () { $this->fail('Should not cancel'); },
        );

        $this->assertCount(3, $tokens);
        $this->assertEquals('Hello', $tokens[0]->text);
        $this->assertEquals(' world', $tokens[1]->text);
        $this->assertEquals('!', $tokens[2]->text);
        $this->assertEquals(1, $tokens[0]->sequence);
        $this->assertEquals(3, $tokens[2]->sequence);
        $this->assertNotNull($completed);
        $this->assertEquals('stop', $completed->finishReason);
        $this->assertEquals(10, $completed->promptTokens);
        $this->assertEquals(5, $completed->completionTokens);
    }

    /**
     * Фикстура: пустой поток с ошибкой от провайдера.
     */
    #[Test]
    public function empty_stream_with_error(): void
    {
        $sse = implode("\n\n", [
            'data: {"error":{"code":"context_length_exceeded","message":"This model maximum context length is 8192"}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake([
            'openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $error = null;
        $tokens = [];

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: function (StreamToken $t) use (&$tokens) { $tokens[] = $t; },
            onCompleted: function () { $this->fail('Should not complete'); },
            onError: function (UpstreamError $e) use (&$error) { $error = $e; },
            onCancel: function () { $this->fail('Should not cancel'); },
        );

        $this->assertEmpty($tokens);
        $this->assertNotNull($error);
        $this->assertEquals('context_exceeded', $error->code);
        $this->assertFalse($error->retryable);
    }

    /**
     * Фикстура: rate limit (429).
     */
    #[Test]
    public function rate_limit_response(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response('Rate limit exceeded', 429),
        ]);

        $error = null;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: function () { $this->fail('Should not receive tokens'); },
            onCompleted: function () { $this->fail('Should not complete'); },
            onError: function (UpstreamError $e) use (&$error) { $error = $e; },
            onCancel: function () { $this->fail('Should not cancel'); },
        );

        $this->assertNotNull($error);
        $this->assertEquals('rate_limit', $error->code);
        $this->assertTrue($error->retryable);
    }

    /**
     * Фикстура: обрыв соединения после частичного ответа.
     */
    #[Test]
    public function connection_break_after_partial_response(): void
    {
        // Частичный SSE — нет [DONE], нет finish_reason=stop
        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Hello"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":" wor"},"finish_reason":null}]}',
        ])."\n\n";

        Http::fake([
            'openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $tokens = [];
        $completed = null;
        $error = null;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: function (StreamToken $t) use (&$tokens) { $tokens[] = $t; },
            onCompleted: function (StreamCompleted $c) use (&$completed) { $completed = $c; },
            onError: function (UpstreamError $e) use (&$error) { $error = $e; },
            onCancel: function () {},
        );

        // Получили частичные токены, completed не вызван, но error вызван
        $this->assertCount(2, $tokens);
        $this->assertNull($completed);
        $this->assertNotNull($error);
        $this->assertEquals('stream_interrupted', $error->code);
        $this->assertTrue($error->retryable);
    }

    /**
     * Фикстура: завершение по finish_reason=length (context limit).
     */
    #[Test]
    public function finish_reason_length(): void
    {
        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Hello"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{},"finish_reason":"length"}],"usage":{"prompt_tokens":10,"completion_tokens":5}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake([
            'openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $tokens = [];
        $completed = null;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: function (StreamToken $t) use (&$tokens) { $tokens[] = $t; },
            onCompleted: function (StreamCompleted $c) use (&$completed) { $completed = $c; },
            onError: function () { $this->fail('Should not error'); },
            onCancel: function () { $this->fail('Should not cancel'); },
        );

        $this->assertCount(1, $tokens);
        $this->assertNotNull($completed);
        $this->assertEquals('length', $completed->finishReason);
    }

    /**
     * Фикстура: cancel после частичного ответа.
     */
    #[Test]
    public function cancel_after_partial_response(): void
    {
        $sse = implode("\n\n", [
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":"Hello"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{"content":" world"},"finish_reason":null}]}',
            'data: {"id":"chatcmpl-1","choices":[{"delta":{},"finish_reason":"stop"}],"usage":{"prompt_tokens":5,"completion_tokens":2}}',
            'data: [DONE]',
        ])."\n\n";

        Http::fake([
            'openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $tokens = [];
        $cancelled = false;
        $callCount = 0;

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: function (StreamToken $t) use (&$tokens) { $tokens[] = $t; },
            onCompleted: function () { $this->fail('Should not complete after cancel'); },
            onError: function () { $this->fail('Should not error'); },
            onCancel: function () use (&$cancelled) { $cancelled = true; },
            shouldCancel: function () use (&$callCount) {
                $callCount++;

                return $callCount >= 2;
            },
        );

        $this->assertTrue($cancelled);
        $this->assertLessThanOrEqual(1, count($tokens));
    }
}
