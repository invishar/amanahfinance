<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\SavingsGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoal>
 */
class SavingsGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $target = fake()->numberBetween(5_000_000, 100_000_000);

        return [
            'family_id' => Family::factory(),
            'target_name' => fake()->randomElement(['Dana Darurat', 'Liburan Keluarga', 'DP Rumah', 'Umroh']),
            'target_amount' => $target,
            'current_amount' => fake()->numberBetween(0, $target),
            'deadline' => fake()->boolean(70) ? fake()->dateTimeBetween('+3 months', '+2 years') : null,
            'icon' => null,
            'color' => fake()->safeHexColor(),
            'account_id' => null,
            'status' => 'active',
            'created_at' => now(),
            'achieved_at' => null,
        ];
    }

    /**
     * Indicate that the goal has been achieved.
     */
    public function achieved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'achieved',
            'current_amount' => $attributes['target_amount'],
            'achieved_at' => now(),
        ]);
    }
}
