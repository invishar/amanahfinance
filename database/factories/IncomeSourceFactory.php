<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\IncomeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeSource>
 */
class IncomeSourceFactory extends Factory
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
            // Suffixed with a unique number: `name` is unique per family, and this
            // fixed pool would otherwise collide whenever a test creates more than
            // one income source per family.
            'name' => fake()->randomElement(['Gaji Bulanan', 'Freelance Desain', 'Bisnis Kecil', 'Investasi']).' '.fake()->unique()->numerify('####'),
            'owner_member_id' => null,
            'expected_amount' => fake()->numberBetween(2_000_000, 25_000_000),
            'cadence' => fake()->randomElement(['monthly', 'biweekly', 'weekly', 'irregular']),
            'is_archived' => false,
            'created_at' => now(),
        ];
    }
}
