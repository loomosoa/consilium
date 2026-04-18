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
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerationCancelControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cancel_pending_generation(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::WAITING]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $msg->id, 'status' => GenerationStatus::PENDING]);

        $response = $this->post("/api/generations/{$generation->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('generation.status', 'cancelled');

        $generation->refresh();
        $this->assertEquals(GenerationStatus::CANCELLED, $generation->status);
        $this->assertEquals(ColumnStatus::CANCELLED, $column->refresh()->status);
    }

    #[Test]
    public function cancel_streaming_generation(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1, 'status' => ColumnStatus::STREAMING]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::STREAMING,
            'partial_output' => 'Partial response text',
            'started_at' => now(),
        ]);

        $response = $this->post("/api/generations/{$generation->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('generation.status', 'cancelled');

        $generation->refresh();
        $this->assertEquals(GenerationStatus::CANCELLED, $generation->status);
        $this->assertEquals('Partial response text', $generation->partial_output);
        $this->assertEquals(ColumnStatus::CANCELLED, $column->refresh()->status);
    }

    #[Test]
    public function cancel_does_not_create_assistant_message(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::STREAMING,
            'partial_output' => 'Some partial text',
        ]);

        $this->post("/api/generations/{$generation->id}/cancel");

        $assistantCount = Message::where('column_id', $column->id)
            ->where('role', MessageRole::ASSISTANT)
            ->count();

        $this->assertEquals(0, $assistantCount);
    }

    #[Test]
    public function cancel_completed_generation_returns_422(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $msg->id, 'status' => GenerationStatus::COMPLETED]);

        $response = $this->post("/api/generations/{$generation->id}/cancel");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Generation is not active']);
    }

    #[Test]
    public function cancel_error_generation_returns_422(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $msg->id, 'status' => GenerationStatus::ERROR]);

        $response = $this->post("/api/generations/{$generation->id}/cancel");

        $response->assertStatus(422);
    }

    #[Test]
    public function cancel_already_cancelled_generation_returns_422(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create(['column_id' => $column->id, 'user_message_id' => $msg->id, 'status' => GenerationStatus::CANCELLED]);

        $response = $this->post("/api/generations/{$generation->id}/cancel");

        $response->assertStatus(422);
    }

    #[Test]
    public function cancel_nonexistent_generation_returns_404(): void
    {
        $response = $this->post('/api/generations/nonexistent-id/cancel');

        $response->assertStatus(404);
    }

    #[Test]
    public function cancel_preserves_partial_output(): void
    {
        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::STREAMING,
            'partial_output' => 'This is a partial response that should be preserved',
        ]);

        $this->post("/api/generations/{$generation->id}/cancel");

        $generation->refresh();
        $this->assertEquals('This is a partial response that should be preserved', $generation->partial_output);
        $this->assertNotNull($generation->completed_at);
    }

    #[Test]
    public function cancel_logs_operation(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Generation cancel requested'
                    && isset($context['generation_id'])
                    && isset($context['status'])
                    && isset($context['column_id']);
            });

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Generation cancelled'
                    && isset($context['generation_id'])
                    && isset($context['partial_output_length']);
            });

        $workspace = Workspace::create(['session_id' => 'test', 'initial_prompt' => 'Hello']);
        $column = ColumnConversation::create(['workspace_id' => $workspace->id, 'model_code' => 'nvidia', 'position' => 1]);
        $msg = Message::create(['column_id' => $column->id, 'role' => 'user', 'content' => 'Hello', 'sequence' => 1]);
        $generation = Generation::create([
            'column_id' => $column->id,
            'user_message_id' => $msg->id,
            'status' => GenerationStatus::STREAMING,
        ]);

        $this->post("/api/generations/{$generation->id}/cancel");
    }
}
