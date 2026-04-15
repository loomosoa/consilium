<?php

namespace Tests\Unit;

use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\ModelDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspacePropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prop. 03: создание workspace порождает ровно 4 колонки с разными моделями.
     */
    #[Test]
    public function workspace_with_4_columns_has_unique_model_codes(): void
    {
        $service = new ModelDefinitionService;
        $models = $service->active();

        $workspace = Workspace::create([
            'session_id' => 'test-session-123',
            'initial_prompt' => 'Test prompt',
        ]);

        foreach ($models as $model) {
            ColumnConversation::create([
                'workspace_id' => $workspace->id,
                'model_code' => $model['code'],
                'position' => $model['order'],
            ]);
        }

        $columns = $workspace->columns()->get();

        $this->assertCount(4, $columns);

        $codes = $columns->pluck('model_code')->toArray();
        $this->assertCount(4, array_unique($codes));
    }

    /**
     * Prop. 04: первое сообщение каждой колонки — исходный промпт workspace.
     */
    #[Test]
    public function first_message_in_each_column_equals_initial_prompt(): void
    {
        $service = new ModelDefinitionService;
        $models = $service->active();
        $prompt = 'Explain quantum computing';

        $workspace = Workspace::create([
            'session_id' => 'test-session-123',
            'initial_prompt' => $prompt,
        ]);

        foreach ($models as $model) {
            $column = ColumnConversation::create([
                'workspace_id' => $workspace->id,
                'model_code' => $model['code'],
                'position' => $model['order'],
            ]);

            Message::create([
                'column_id' => $column->id,
                'role' => 'user',
                'content' => $prompt,
                'sequence' => 1,
            ]);
        }

        foreach ($workspace->columns as $column) {
            $firstMessage = $column->messages()->orderBy('sequence')->first();

            $this->assertNotNull($firstMessage);
            $this->assertEquals(MessageRole::USER, $firstMessage->role);
            $this->assertEquals($prompt, $firstMessage->content);
            $this->assertEquals(1, $firstMessage->sequence);
        }
    }

    /**
     * Prop. 03 variant: для любого промпта — 4 generation с одинаковым текстом, разными моделями.
     */
    #[Test]
    public function four_generations_created_with_same_user_message_different_models(): void
    {
        $service = new ModelDefinitionService;
        $models = $service->active();
        $prompt = 'Analyze this problem';

        $workspace = Workspace::create([
            'session_id' => 'test-session-123',
            'initial_prompt' => $prompt,
        ]);

        $generationIds = [];

        foreach ($models as $model) {
            $column = ColumnConversation::create([
                'workspace_id' => $workspace->id,
                'model_code' => $model['code'],
                'position' => $model['order'],
            ]);

            $userMessage = Message::create([
                'column_id' => $column->id,
                'role' => 'user',
                'content' => $prompt,
                'sequence' => 1,
            ]);

            $generation = Generation::create([
                'column_id' => $column->id,
                'user_message_id' => $userMessage->id,
            ]);

            $generationIds[] = $generation->id;
        }

        $this->assertCount(4, $generationIds);
        $this->assertCount(4, array_unique($generationIds));

        foreach (Generation::whereIn('id', $generationIds)->get() as $generation) {
            $this->assertEquals(GenerationStatus::PENDING, $generation->status);
            $this->assertEquals($prompt, $generation->userMessage->content);
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
            'long' => [str_repeat('a', 50000)],
        ];
    }

    #[DataProvider('promptProvider')]
    #[Test]
    public function workspace_structure_invariant_for_any_prompt(string $prompt): void
    {
        $service = new ModelDefinitionService;
        $models = $service->active();

        $workspace = Workspace::create([
            'session_id' => 'test-session-123',
            'initial_prompt' => $prompt,
        ]);

        foreach ($models as $model) {
            ColumnConversation::create([
                'workspace_id' => $workspace->id,
                'model_code' => $model['code'],
                'position' => $model['order'],
            ]);
        }

        $this->assertCount(4, $workspace->columns);

        $codes = $workspace->columns->pluck('model_code')->toArray();
        $expectedCodes = array_column($models, 'code');
        sort($codes);
        sort($expectedCodes);
        $this->assertEquals($expectedCodes, $codes);
    }
}
