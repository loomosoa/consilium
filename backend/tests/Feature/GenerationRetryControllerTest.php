<?php

namespace Tests\Feature;

use App\Enums\ColumnStatus;
use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerationRetryControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function retry_error_generation_creates_new_generation(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::ERROR]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::ERROR,
            'error_code' => 'rate_limit',
            'error_message' => 'Rate limit exceeded',
            'retryable' => true,
        ]);

        $response = $this->post("/api/generations/{$generation->id}/retry");

        $response->assertStatus(201);
        $response->assertJsonPath('generation.status', 'pending');

        // New generation created with same user_message_id
        $newGeneration = Generation::where('id', '!=', $generation->id)
            ->where('column_id', $column->id)
            ->first();

        $this->assertNotNull($newGeneration);
        $this->assertEquals($msg->id, $newGeneration->user_message_id);
        $this->assertEquals(GenerationStatus::PENDING, $newGeneration->status);
    }

    #[Test]
    public function retry_cancelled_generation_creates_new_generation(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::CANCELLED]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::CANCELLED,
            'partial_output' => 'Partial text',
        ]);

        $response = $this->post("/api/generations/{$generation->id}/retry");

        $response->assertStatus(201);

        $newGeneration = Generation::where('id', '!=', $generation->id)
            ->where('column_id', $column->id)
            ->first();

        $this->assertNotNull($newGeneration);
        $this->assertEquals(GenerationStatus::PENDING, $newGeneration->status);
    }

    #[Test]
    public function retry_updates_column_status_to_waiting(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::ERROR]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::ERROR,
        ]);

        $this->post("/api/generations/{$generation->id}/retry");

        $this->assertEquals(ColumnStatus::WAITING, $column->refresh()->status);
    }

    #[Test]
    public function retry_completed_generation_returns_422(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::COMPLETED,
        ]);

        $response = $this->post("/api/generations/{$generation->id}/retry");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Retry is only allowed for error or cancelled generations']);
    }

    #[Test]
    public function retry_pending_generation_returns_422(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::PENDING,
        ]);

        $response = $this->post("/api/generations/{$generation->id}/retry");

        $response->assertStatus(422);
    }

    #[Test]
    public function retry_streaming_generation_returns_422(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::STREAMING,
        ]);

        $response = $this->post("/api/generations/{$generation->id}/retry");

        $response->assertStatus(422);
    }

    #[Test]
    public function retry_nonexistent_generation_returns_404(): void
    {
        $response = $this->post('/api/generations/nonexistent-id/retry');

        $response->assertStatus(404);
    }

    #[Test]
    public function retry_with_active_generation_in_column_returns_422(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);

        // Failed generation
        $failedGeneration = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::ERROR,
        ]);

        // Active generation in same column
        Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::PENDING,
        ]);

        $response = $this->post("/api/generations/{$failedGeneration->id}/retry");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Column already has an active generation']);
    }

    #[Test]
    public function retry_does_not_include_partial_output_in_context(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::ERROR]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);

        // Failed generation with partial output
        $failedGeneration = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::ERROR,
            'partial_output' => 'This partial output should not be in context',
        ]);

        $this->post("/api/generations/{$failedGeneration->id}/retry");

        // New generation should not have partial_output
        $newGeneration = Generation::where('id', '!=', $failedGeneration->id)
            ->where('column_id', $column->id)
            ->first();

        $this->assertNotNull($newGeneration);
        $this->assertNull($newGeneration->partial_output);

        // Confirmed history should not include partial output
        $conversationService = $this->app->make(\App\Services\ColumnConversationService::class);
        $history = $conversationService->getConfirmedHistory($column);
        $historyText = implode('', array_map(fn (Message $m) => $m->content, $history));
        $this->assertStringNotContainsString('This partial output should not be in context', $historyText);
    }
}
