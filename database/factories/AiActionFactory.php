<?php

namespace Database\Factories;

use App\Models\AiAction;
use App\Models\ChatMessage;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAction>
 */
class AiActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => ChatMessage::factory(),
            'family_id' => Family::factory(),
            'action' => fake()->randomElement([
                'create_transaction', 'create_wallet', 'create_account',
                'create_income_source', 'create_savings_goal', 'advice',
            ]),
            'payload' => [
                'summary' => fake()->sentence(),
            ],
            'status' => 'pending',
            'result_table' => null,
            'result_id' => null,
            'confidence' => fake()->randomFloat(2, 0.5, 1),
            'resolved_at' => null,
            'resolved_by' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the action has been confirmed by a family member.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'resolved_at' => now(),
        ]);
    }
}
