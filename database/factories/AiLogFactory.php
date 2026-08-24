<?php

namespace Database\Factories;

use App\Models\AiLog;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiLog>
 */
class AiLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'thread_id' => null,
            'message_id' => null,
            'model' => 'claude-sonnet-4-5',
            'user_prompt' => fake()->sentence(),
            'system_prompt' => fake()->paragraph(),
            'input_tokens' => fake()->numberBetween(100, 2000),
            'output_tokens' => fake()->numberBetween(10, 500),
            'created_at' => now(),
        ];
    }
}
