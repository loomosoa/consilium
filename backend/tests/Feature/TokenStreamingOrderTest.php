<?php

namespace Tests\Feature;

use App\DTOs\StreamToken;
use App\Enums\GenerationStatus;
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

class TokenStreamingOrderTest extends TestCase
{
    use RefreshDatabase;

    private Generation $generation;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-api-key']);

        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create([
            'workspace_id' => $workspace->id,
            'model_code' => 'nvidia',
            'position' => 1,
        ]);
        $msg = Message::create([
            'column_id' => $column->id,
            'role' => 'user',
            'content' => 'Hello',
            'sequence' => 1,
        ]);
        $this->generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::PENDING,
        ]);
    }

    private function fakeOpenRouterSse(array $tokenTexts): void
    {
        $lines = [];
        foreach ($tokenTexts as $i => $text) {
            $lines[] = 'data: ' . json_encode([
                'id' => 'chatcmpl-1',
                'choices' => [['delta' => ['content' => $text], 'finish_reason' => null]],
            ]);
        }
        $lines[] = 'data: ' . json_encode([
            'id' => 'chatcmpl-1',
            'choices' => [['delta' => [], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => count($tokenTexts)],
        ]);
        $lines[] = 'data: [DONE]';

        $sse = implode("\n\n", $lines) . "\n\n";

        Http::fake(['openrouter.ai/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);
    }

    #[Test]
    public function tokens_are_emitted_incrementally_not_batched_with_completed(): void
    {
        $tokenTexts = ['Hello', ' ', 'world', '!', ' How', ' are', ' you', '?'];
        $this->fakeOpenRouterSse($tokenTexts);

        $events = $this->captureStreamEvents();

        // Каждый токен — отдельное SSE-событие, completed — в самом конце
        $tokenEvents = array_filter($events, fn (array $e) => $e['type'] === 'token');
        $completedEvents = array_filter($events, fn (array $e) => $e['type'] === 'completed');

        $this->assertCount(count($tokenTexts), $tokenEvents, 'Each upstream token must produce a separate token SSE event');
        $this->assertCount(1, $completedEvents, 'Exactly one completed event expected');

        // completed идёт после всех token
        $lastTokenIndex = array_key_last($tokenEvents);
        $completedIndex = array_key_first($completedEvents);
        $this->assertGreaterThan($lastTokenIndex, $completedIndex, 'Completed event must come after all token events');
    }

    #[Test]
    public function token_sequence_numbers_are_incremental(): void
    {
        $tokenTexts = ['One', ' Two', ' Three'];
        $this->fakeOpenRouterSse($tokenTexts);

        $events = $this->captureStreamEvents();
        $tokenEvents = array_values(array_filter($events, fn (array $e) => $e['type'] === 'token'));

        $sequences = array_map(fn (array $e) => $e['data']->sequence, $tokenEvents);

        $this->assertEquals([1, 2, 3], $sequences, 'Token sequence numbers must be 1-based incremental');
    }

    #[Test]
    public function token_texts_match_upstream_order(): void
    {
        $tokenTexts = ['First', ' ', 'Second', ' ', 'Third'];
        $this->fakeOpenRouterSse($tokenTexts);

        $events = $this->captureStreamEvents();
        $tokenEvents = array_values(array_filter($events, fn (array $e) => $e['type'] === 'token'));

        $texts = array_map(fn (array $e) => $e['data']->text, $tokenEvents);

        $this->assertSame($tokenTexts, $texts, 'Token texts must preserve upstream order');
    }

    #[Test]
    public function sendSse_is_called_for_each_token_before_completed(): void
    {
        $tokenTexts = ['A', 'B', 'C'];
        $this->fakeOpenRouterSse($tokenTexts);

        $callLog = [];
        $sendSse = function (string $type, mixed $data) use (&$callLog): void {
            $callLog[] = ['type' => $type, 'ordinal' => count($callLog) + 1];
        };

        $service = app(GenerationService::class);
        $service->streamGeneration($this->generation, $sendSse, fn () => false);

        // Порядок вызовов: token, token, token, completed
        $types = array_column($callLog, 'type');

        $this->assertSame(['token', 'token', 'token', 'completed'], $types,
            'sendSse must be called for each token immediately, then once for completed — no batching');
    }

    #[Test]
    public function partial_output_accumulates_across_tokens(): void
    {
        $tokenTexts = ['Hello', ' ', 'world'];
        $this->fakeOpenRouterSse($tokenTexts);

        $events = $this->captureStreamEvents();

        // После завершения generation должна содержать полный текст
        $this->generation->refresh();
        $this->assertEquals('Hello world', $this->generation->partial_output);
        $this->assertEquals(GenerationStatus::COMPLETED, $this->generation->status);
    }

    #[Test]
    public function single_token_stream_produces_one_token_then_completed(): void
    {
        $this->fakeOpenRouterSse(['Solo']);

        $events = $this->captureStreamEvents();
        $types = array_column($events, 'type');

        $this->assertSame(['token', 'completed'], $types);
    }

    /**
     * Запускает streamGeneration и захватывает все SSE-события с данными.
     *
     * @return array<int, array{type: string, data: mixed}>
     */
    private function captureStreamEvents(): array
    {
        $sseFactory = app(SseEventFactory::class);
        $events = [];

        $sendSse = function (string $type, mixed $data) use ($sseFactory, &$events): void {
            $events[] = ['type' => $type, 'data' => $data];
        };

        $service = app(GenerationService::class);
        $service->streamGeneration($this->generation, $sendSse, fn () => false);

        return $events;
    }
}
