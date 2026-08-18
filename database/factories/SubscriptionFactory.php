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
        // starts_at & durasi diacak (bukan selalu now()+30 hari) supaya banyak
        // subscription 'active' yang dibuat berdekatan (mis. lewat seeder)
        // tidak semuanya jatuh tempo di tanggal yang sama persis.
        return $this->state(function () {
            $startsAt = now()->subDays(fake()->numberBetween(0, 20));

            return [
                'status' => 'active',
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addDays(fake()->randomElement([30, 90, 365])),
                'reviewed_at' => $startsAt,
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(function () {
            $endsAt = now()->subDays(fake()->numberBetween(1, 45));
            $startsAt = $endsAt->copy()->subDays(fake()->randomElement([30, 90, 365]));

            return [
                'status' => 'expired',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reviewed_at' => $startsAt,
            ];
        });
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
