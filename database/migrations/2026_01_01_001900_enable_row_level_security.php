<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jaring pengaman kedua di bawah global scope Eloquent.
     * Middleware ResolveFamily menjalankan:
     *   set_config('app.current_family_id', <uuid>, true)
     * pada setiap request; koneksi migrasi/queue memakai role bypass RLS.
     */
    private array $tables = [
        'families', 'family_members', 'family_invites', 'accounts', 'wallets',
        'income_sources', 'transactions', 'recurring_rules', 'savings_goals',
        'chat_threads', 'ai_actions', 'onboarding_answers', 'notifications', 'audit_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            $column = $t === 'families' ? 'id' : 'family_id';

            DB::statement("alter table {$t} enable row level security");
            DB::statement("
                create policy {$t}_family_isolation on {$t}
                using ({$column}::text = current_setting('app.current_family_id', true))
                with check ({$column}::text = current_setting('app.current_family_id', true))
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            DB::statement("drop policy if exists {$t}_family_isolation on {$t}");
            DB::statement("alter table {$t} disable row level security");
        }
    }
};
