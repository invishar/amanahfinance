<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            create view v_wallet_month as
            select w.id       as wallet_id,
                   w.family_id,
                   date_trunc('month', t.transaction_date)::date as period,
                   coalesce(b.amount, w.monthly_budget)          as budget,
                   coalesce(sum(t.amount), 0)                    as spent
            from wallets w
            left join wallet_budgets b on b.wallet_id = w.id
                 and b.period = date_trunc('month', current_date)::date
            left join transactions t on t.wallet_id = w.id
                 and t.type = 'expense' and t.deleted_at is null
            group by w.id, w.family_id, period, b.amount
        ");

        DB::statement("
            create view v_cashflow_month as
            select family_id,
                   date_trunc('month', transaction_date)::date as period,
                   sum(amount) filter (where type = 'income')  as total_income,
                   sum(amount) filter (where type = 'expense') as total_expense,
                   sum(amount) filter (where type = 'savings') as total_savings
            from transactions
            where deleted_at is null
            group by family_id, period
        ");
    }

    public function down(): void
    {
        DB::statement('drop view if exists v_cashflow_month');
        DB::statement('drop view if exists v_wallet_month');
    }
};
