<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters: each seeder below depends on records created by the
     * ones before it (families need users, transactions need accounts, etc).
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            FamilySeeder::class,
            FamilyMemberSeeder::class,
            FamilyInviteSeeder::class,
            AccountSeeder::class,
            WalletSeeder::class,
            WalletBudgetSeeder::class,
            IncomeSourceSeeder::class,
            SavingsGoalSeeder::class,
            TransactionSeeder::class,
            RecurringRuleSeeder::class,
            ChatThreadSeeder::class,
            ChatMessageSeeder::class,
            AiActionSeeder::class,
            OnboardingAnswerSeeder::class,
            NotificationSeeder::class,
            AuditLogSeeder::class,
        ]);
    }
}
