<?php

namespace Database\Factories;

use App\Models\ColumnConversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
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
            'role' => fake()->randomElement(['user', 'assistant', 'system']),
            'content' => fake()->paragraph(),
            'sequence' => 1,
            'generation_id' => null,
        ];
    }
}
