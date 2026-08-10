<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\RecurringRule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RecurringRuleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Family::all()->each(function (Family $family) {
            $accounts = $family->accounts;
            $wallets = $family->wallets;

            if ($accounts->isEmpty() || $wallets->isEmpty()) {
                return;
            }

            RecurringRule::factory(2)->create([
                'family_id' => $family->id,
                'account_id' => fn () => $accounts->random()->id,
                'wallet_id' => fn () => $wallets->random()->id,
            ]);
        });
    }
}
