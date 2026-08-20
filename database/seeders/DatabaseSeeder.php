<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
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
            SubscriptionPlanSeeder::class,
            UserSeeder::class,
            LlmSettingSeeder::class,
            FamilySeeder::class,
            FamilyMemberSeeder::class,
            FamilyInviteSeeder::class,
            SubscriptionSeeder::class,
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

        $this->printCredentialSummary();
    }

    /**
     * created_by di transactions menunjuk ke family_members, bukan users
     * langsung, jadi jumlah transaksi per user baru bisa dihitung setelah
     * seeding selesai (TransactionSeeder memilih member secara acak).
     */
    private function printCredentialSummary(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $topUser = Transaction::query()
            ->join('family_members', 'family_members.id', '=', 'transactions.created_by')
            ->join('users', 'users.id', '=', 'family_members.user_id')
            ->selectRaw('users.email, users.full_name, count(*) as tx_count')
            ->groupBy('users.id', 'users.email', 'users.full_name')
            ->orderByDesc('tx_count')
            ->first();

        $appUrl = rtrim(config('app.url'), '/');

        $this->command?->newLine();

        if ($admin) {
            $this->command?->line("{$appUrl}/admin/login");
            $this->command?->line("Sample user Admin: {$admin->email} / pass: password");
            $this->command?->newLine();
        }

        if ($topUser) {
            $this->command?->line("{$appUrl}/login");
            $this->command?->line("Sample user dengan banyak transaksi : {$topUser->email} ({$topUser->full_name}) - {$topUser->tx_count} transaksi / pass: password");
        }
    }
}
