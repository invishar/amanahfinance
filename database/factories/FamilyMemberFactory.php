<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['admin', 'member', 'viewer']),
            'nickname' => fake()->randomElement(['Ayah', 'Bunda', 'Kakak', 'Adik', null]),
            'monthly_quota' => fake()->boolean(40) ? fake()->numberBetween(500_000, 5_000_000) : null,
            'joined_at' => now(),
            'removed_at' => null,
        ];
    }

    /**
     * Indicate that the member has left the family.
     */
    public function removed(): static
    {
        return $this->state(fn (array $attributes) => [
            'removed_at' => now(),
        ]);
    }
}
