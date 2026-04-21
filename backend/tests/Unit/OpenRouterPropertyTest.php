<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\StreamCompleted;
use App\DTOs\StreamToken;
use App\DTOs\UpstreamError;
use App\Models\ColumnConversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\OpenRouterClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property 17: Любой запрос к модели проходит только через OpenRouter.
 */
class OpenRouterPropertyTest extends TestCase
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
     * Для любого запроса к модели, все HTTP-запросы идут только к openrouter.ai.
     */
    #[Test]
    public function all_model_requests_go_through_openrouter_only(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(
                "data: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{\"content\":\"Hi\"},\"finish_reason\":null}]}\n\ndata: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":1,\"completion_tokens\":1}}\n\ndata: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'test']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);

        $this->client->stream(
            openRouterModelId: 'nvidia/llama-3.1-nemotron-70b-instruct',
            messages: [$msg],
            onToken: function (StreamToken $t) {},
            onCompleted: function (StreamCompleted $c) {},
            onError: function (UpstreamError $e) {},
            onCancel: function () {},
        );

        // Все HTTP-запросы должны идти только к openrouter.ai
        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://openrouter.ai/api/v1/');
        });

        // Не должно быть запросов к другим доменам
        Http::assertNotSent(function ($request) {
            return ! str_starts_with($request->url(), 'https://openrouter.ai/api/v1/');
        });
    }

    /**
     * Для любого запроса к модели, Authorization header содержит Bearer token.
     */
    #[Test]
    public function all_model_requests_include_bearer_auth(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(
                "data: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":1,\"completion_tokens\":1}}\n\ndata: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $this->client->stream(
            openRouterModelId: 'test-model',
            messages: [],
            onToken: function (StreamToken $t) {},
            onCompleted: function (StreamCompleted $c) {},
            onError: function (UpstreamError $e) {},
            onCancel: function () {},
        );

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-api-key');
        });
    }

    /**
     * Для любого запроса к модели, payload содержит model и stream=true.
     */
    #[Test]
    public function all_model_requests_include_model_and_stream_flag(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(
                "data: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":1,\"completion_tokens\":1}}\n\ndata: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $this->client->stream(
            openRouterModelId: 'test-model-id',
            messages: [],
            onToken: function (StreamToken $t) {},
            onCompleted: function (StreamCompleted $c) {},
            onError: function (UpstreamError $e) {},
            onCancel: function () {},
        );

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['model'] === 'test-model-id' && $data['stream'] === true;
        });
    }
}
