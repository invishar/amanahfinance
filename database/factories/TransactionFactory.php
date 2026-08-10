<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Family;
use App\Models\IncomeSource;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state (an expense transaction).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'type' => 'expense',
            'amount' => fake()->numberBetween(10_000, 500_000),
            'transaction_date' => fake()->dateTimeBetween('-2 months', 'now'),
            'account_id' => Account::factory(),
            'to_account_id' => null,
            'wallet_id' => Wallet::factory(),
            'source_id' => null,
            'goal_id' => null,
            'note' => fake()->optional()->sentence(),
            'created_by' => null,
            'origin' => 'manual',
            'receipt_url' => null,
        ];
    }

    /**
     * Indicate that the transaction is an income entry.
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
            'wallet_id' => null,
            'source_id' => IncomeSource::factory(),
        ]);
    }

    /**
     * Indicate that the transaction is a transfer between accounts.
     */
    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'transfer',
            'wallet_id' => null,
            'account_id' => Account::factory(),
            'to_account_id' => Account::factory(),
        ]);
    }

    /**
     * Indicate that the transaction funds a savings goal.
     */
    public function savings(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'savings',
            'wallet_id' => null,
            'goal_id' => SavingsGoal::factory(),
        ]);
    }
}
