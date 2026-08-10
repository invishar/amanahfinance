<?php

namespace Database\Factories;

use App\Models\Wallet;
use App\Models\WalletBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletBudget>
 */
class WalletBudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'period' => now()->startOfMonth(),
            'amount' => fake()->numberBetween(200_000, 3_000_000),
            'created_at' => now(),
        ];
    }
}
