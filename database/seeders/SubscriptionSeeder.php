<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = SubscriptionPlan::all();
        $reviewer = User::query()->where('is_platform_admin', true)->first()
            ?? User::query()->first();

        if ($plans->isEmpty()) {
            return;
        }

        Family::all()->each(function (Family $family) use ($plans, $reviewer) {
            $requester = $family->members()->inRandomOrder()->first();
            $plan = $plans->random();

            $state = fake()->randomElement(['active', 'expired', 'pending_payment', 'rejected']);

            $subscription = match ($state) {
                'active' => Subscription::factory()->active(),
                'expired' => Subscription::factory()->expired(),
                'rejected' => Subscription::factory()->rejected(),
                default => Subscription::factory(),
            };

            $subscription->create([
                'family_id' => $family->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'requested_by' => $requester?->id,
                'reviewed_by' => $state === 'pending_payment' ? null : $reviewer?->id,
            ]);
        });
    }
}
