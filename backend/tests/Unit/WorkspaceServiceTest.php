<?php

namespace Tests\Unit;

use App\DTOs\WorkspaceResponse;
use App\Enums\ColumnStatus;
use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Enums\WorkspaceState;
use App\Models\ColumnConversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkspaceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(WorkspaceService::class);
    }

    #[Test]
    public function creates_workspace_with_4_columns(): void
    {
        $response = $this->service->create('test-session', 'Hello world');

        $this->assertInstanceOf(WorkspaceResponse::class, $response);
        $this->assertCount(4, $response->columns);
    }

    #[Test]
    public function columns_have_unique_model_codes(): void
    {
        $response = $this->service->create('test-session', 'Hello world');

        $codes = array_map(fn ($c) => $c->modelCode, $response->columns);
        $this->assertCount(4, array_unique($codes));
    }

    #[Test]
    public function columns_are_ordered_by_position(): void
    {
        $response = $this->service->create('test-session', 'Hello world');

        $positions = array_map(fn ($c) => $c->position, $response->columns);
        $sorted = $positions;
        sort($sorted);
        $this->assertEquals($sorted, $positions);
    }

    #[Test]
    public function first_message_in_each_column_equals_initial_prompt(): void
    {
        $prompt = 'Explain quantum computing';
        $response = $this->service->create('test-session', $prompt);

        foreach ($response->columns as $columnDto) {
            $column = ColumnConversation::find($columnDto->id);
            $firstMessage = $column->messages()->orderBy('sequence')->first();

            $this->assertNotNull($firstMessage);
            $this->assertEquals(MessageRole::USER, $firstMessage->role);
            $this->assertEquals($prompt, $firstMessage->content);
            $this->assertEquals(1, $firstMessage->sequence);
        }
    }

    #[Test]
    public function creates_4_generations_in_pending_status(): void
    {
        $response = $this->service->create('test-session', 'Hello world');

        $this->assertCount(4, $response->generations);

        foreach ($response->generations as $genDto) {
            $this->assertEquals(GenerationStatus::PENDING->value, $genDto->status);
        }
    }

    #[Test]
    public function each_generation_references_correct_column_and_message(): void
    {
        $response = $this->service->create('test-session', 'Hello world');

        $columnIds = array_map(fn ($c) => $c->id, $response->columns);

        foreach ($response->generations as $genDto) {
            $this->assertContains($genDto->columnId, $columnIds);

            $message = Message::find($genDto->userMessageId);
            $this->assertNotNull($message);
            $this->assertEquals(MessageRole::USER, $message->role);
        }
    }

    #[Test]
    public function workspace_state_is_active(): void
    {
        $response = $this->service->create('test-session', 'Hello world');

        $workspace = Workspace::find($response->workspaceId);
        $this->assertEquals(WorkspaceState::ACTIVE, $workspace->state);
    }

    #[Test]
    public function columns_have_waiting_status(): void
    {
        $response = $this->service->create('test-session', 'Hello world');

        foreach ($response->columns as $columnDto) {
            $this->assertEquals(ColumnStatus::WAITING->value, $columnDto->status);
        }
    }

    #[Test]
    public function column_last_generation_is_set(): void
    {
        $response = $this->service->create('test-session', 'Hello world');

        foreach ($response->columns as $columnDto) {
            $column = ColumnConversation::find($columnDto->id);
            $this->assertNotNull($column->last_generation_id);
        }
    }

    #[Test]
    public function find_returns_workspace_response(): void
    {
        $created = $this->service->create('test-session', 'Hello world');
        $found = $this->service->find($created->workspaceId);

        $this->assertNotNull($found);
        $this->assertEquals($created->workspaceId, $found->workspaceId);
        $this->assertCount(4, $found->columns);
    }

    #[Test]
    public function find_returns_null_for_nonexistent_workspace(): void
    {
        $result = $this->service->find('00000000-0000-0000-0000-000000000000');

        $this->assertNull($result);
    }

    #[Test]
    public function prompt_exceeding_max_length_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->create('test-session', str_repeat('a', 100001));
    }

    #[Test]
    public function prompt_within_max_length_succeeds(): void
    {
        $response = $this->service->create('test-session', str_repeat('a', 100000));

        $this->assertInstanceOf(WorkspaceResponse::class, $response);
    }
}
