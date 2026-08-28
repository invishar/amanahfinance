<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\Transaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Family::with(['accounts', 'wallets', 'incomeSources', 'savingsGoals', 'members'])->get()->each(function (Family $family) {
            $accounts = $family->accounts;
            $wallets = $family->wallets;
            $sources = $family->incomeSources;
            $goals = $family->savingsGoals;
            $member = $family->members->isNotEmpty() ? $family->members->random() : null;

            if ($accounts->isEmpty()) {
                return;
            }

            Transaction::factory(15)->create([
                'family_id' => $family->id,
                'account_id' => fn () => $accounts->random()->id,
                'wallet_id' => fn () => $wallets->isNotEmpty() ? $wallets->random()->id : null,
                'created_by' => $member?->id,
            ]);

            if ($sources->isNotEmpty()) {
                Transaction::factory(5)->income()->create([
                    'family_id' => $family->id,
                    'account_id' => fn () => $accounts->random()->id,
                    'source_id' => fn () => $sources->random()->id,
                    'created_by' => $member?->id,
                ]);
            }

            if ($accounts->count() > 1) {
                collect(range(1, 3))->each(function () use ($family, $accounts, $member) {
                    [$from, $to] = $accounts->random(2)->all();

                    Transaction::factory()->transfer()->create([
                        'family_id' => $family->id,
                        'account_id' => $from->id,
                        'to_account_id' => $to->id,
                        'created_by' => $member?->id,
                    ]);
                });
            }

            if ($goals->isNotEmpty()) {
                Transaction::factory(3)->savings()->create([
                    'family_id' => $family->id,
                    'account_id' => fn () => $accounts->random()->id,
                    'goal_id' => fn () => $goals->random()->id,
                    'created_by' => $member?->id,
                ]);
            }
        });
    }
}
