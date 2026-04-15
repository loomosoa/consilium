<?php

namespace Database\Factories;

use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'column_id' => ColumnConversation::factory(),
            'user_message_id' => Message::factory(),
            'status' => 'pending',
            'partial_output' => null,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'error_code' => null,
            'error_message' => null,
            'retryable' => false,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
