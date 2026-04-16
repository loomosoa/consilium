<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspacePropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prop. 03: для любого промпта — 4 generation с одинаковым текстом, разными моделями.
     */
    #[Test]
    public function four_generations_with_same_user_message_different_models(): void
    {
        $service = $this->app->make(WorkspaceService::class);
        $prompt = 'Analyze this problem';

        $response = $service->create('test-session', $prompt);

        $this->assertCount(4, $response->generations);

        foreach ($response->generations as $genDto) {
            $generation = Generation::find($genDto->id);
            $this->assertEquals(GenerationStatus::PENDING, $generation->status);
            $this->assertEquals($prompt, $generation->userMessage->content);
        }

        $columnModelCodes = array_map(fn ($c) => $c->modelCode, $response->columns);
        $this->assertCount(4, array_unique($columnModelCodes));
    }

    /**
     * Prop. 04: первое сообщение каждой колонки === initialPrompt.
     */
    #[Test]
    public function first_message_in_each_column_equals_initial_prompt(): void
    {
        $service = $this->app->make(WorkspaceService::class);
        $prompt = 'Explain quantum computing';

        $response = $service->create('test-session', $prompt);

        foreach ($response->columns as $columnDto) {
            $column = ColumnConversation::find($columnDto->id);
            $firstMessage = $column->messages()->orderBy('sequence')->first();

            $this->assertNotNull($firstMessage);
            $this->assertEquals(MessageRole::USER, $firstMessage->role);
            $this->assertEquals($prompt, $firstMessage->content);
            $this->assertEquals(1, $firstMessage->sequence);
        }
    }

    /**
     * Data provider: различные промпты для property-тестов.
     */
    public static function promptProvider(): array
    {
        return [
            'short' => ['Hi'],
            'medium' => [str_repeat('Hello ', 50)],
            'long' => [str_repeat('a', 30000)],
        ];
    }

    #[DataProvider('promptProvider')]
    #[Test]
    public function workspace_structure_invariant_for_any_prompt(string $prompt): void
    {
        $service = $this->app->make(WorkspaceService::class);

        $response = $service->create('test-session', $prompt);

        $this->assertCount(4, $response->columns);
        $this->assertCount(4, $response->generations);

        $codes = array_map(fn ($c) => $c->modelCode, $response->columns);
        $this->assertCount(4, array_unique($codes));
    }
}
