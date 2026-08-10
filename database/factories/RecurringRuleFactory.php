<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Family;
use App\Models\RecurringRule;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringRule>
 */
class RecurringRuleFactory extends Factory
{
    /**
     * Define the model's default state (a recurring expense).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'type' => 'expense',
            'amount' => fake()->numberBetween(50_000, 2_000_000),
            'wallet_id' => Wallet::factory(),
            'source_id' => null,
            'account_id' => Account::factory(),
            'note' => fake()->optional()->sentence(3),
            'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=1',
            'next_run_on' => now()->addMonthNoOverflow()->startOfMonth(),
            'is_active' => true,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the rule is a recurring income.
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
            'wallet_id' => null,
        ]);
    }
}
