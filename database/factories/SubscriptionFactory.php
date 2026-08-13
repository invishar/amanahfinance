<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'plan_id' => SubscriptionPlan::factory(),
            'status' => 'pending_payment',
            'amount' => fake()->randomElement([49_000, 99_000, 249_000, 999_000]),
            'payment_note' => fake()->boolean(70) ? fake()->sentence() : null,
            'payment_proof_url' => fake()->boolean(70) ? fake()->imageUrl() : null,
            'paid_at' => now(),
            'requested_by' => null,
        ];
    }

    public function active(): static
    {
        $startsAt = now();

        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays(30),
            'reviewed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDay(),
            'reviewed_at' => now()->subDays(60),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'reviewed_at' => now(),
            'review_note' => fake()->sentence(),
        ]);
    }
}
