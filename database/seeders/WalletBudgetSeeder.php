<?php

namespace Database\Seeders;

use App\Models\Wallet;
use App\Models\WalletBudget;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WalletBudgetSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Wallet::all()->each(function (Wallet $wallet) {
            collect([now()->subMonthNoOverflow(), now()])
                ->each(function ($month) use ($wallet) {
                    WalletBudget::factory()->create([
                        'wallet_id' => $wallet->id,
                        'period' => $month->copy()->startOfMonth(),
                        'amount' => $wallet->monthly_budget,
                    ]);
                });
        });
    }
}
