<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
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
            'actor_id' => null,
            'entity' => fake()->randomElement(['transaction', 'wallet', 'account', 'savings_goal']),
            'entity_id' => fake()->uuid(),
            'action' => fake()->randomElement(['create', 'update', 'delete', 'restore']),
            'diff' => ['before' => null, 'after' => ['note' => fake()->sentence()]],
            'created_at' => now(),
        ];
    }
}
