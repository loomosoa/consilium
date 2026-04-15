<?php

namespace Database\Factories;

use App\Models\ColumnConversation;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColumnConversation>
 */
class ColumnConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'model_code' => fake()->randomElement(['xai', 'google', 'zai', 'openai']),
            'title' => null,
            'position' => fake()->numberBetween(1, 4),
            'status' => 'idle',
        ];
    }
}
