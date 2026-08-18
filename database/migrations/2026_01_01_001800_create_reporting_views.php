<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL/MariaDB tidak punya date_trunc() atau agregat FILTER (WHERE ...)
        // seperti Postgres, jadi dipakai DATE_FORMAT(...) untuk potong ke awal
        // bulan dan CASE WHEN di dalam SUM() sebagai ganti FILTER.
        DB::statement("
            create or replace view v_wallet_month as
            select w.id       as wallet_id,
                   w.family_id,
                   cast(date_format(t.transaction_date, '%Y-%m-01') as date) as period,
                   coalesce(b.amount, w.monthly_budget)                      as budget,
                   coalesce(sum(t.amount), 0)                                as spent
            from wallets w
            left join wallet_budgets b on b.wallet_id = w.id
                 and b.period = cast(date_format(curdate(), '%Y-%m-01') as date)
            left join transactions t on t.wallet_id = w.id
                 and t.type = 'expense' and t.deleted_at is null
            group by w.id, w.family_id, cast(date_format(t.transaction_date, '%Y-%m-01') as date), b.amount, w.monthly_budget
        ");

        DB::statement("
            create or replace view v_cashflow_month as
            select family_id,
                   cast(date_format(transaction_date, '%Y-%m-01') as date)      as period,
                   sum(case when type = 'income' then amount end)  as total_income,
                   sum(case when type = 'expense' then amount end) as total_expense,
                   sum(case when type = 'savings' then amount end) as total_savings
            from transactions
            where deleted_at is null
            group by family_id, cast(date_format(transaction_date, '%Y-%m-01') as date)
        ");
    }

    public function down(): void
    {
        DB::statement('drop view if exists v_cashflow_month');
        DB::statement('drop view if exists v_wallet_month');
    }
};
