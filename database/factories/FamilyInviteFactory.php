<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\FamilyInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FamilyInvite>
 */
class FamilyInviteFactory extends Factory
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
            'invited_by' => User::factory(),
            'email' => fake()->safeEmail(),
            'phone' => null,
            'role' => 'member',
            'token' => 'AMANA-'.Str::upper(Str::random(6)),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the invite has already been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }
}
