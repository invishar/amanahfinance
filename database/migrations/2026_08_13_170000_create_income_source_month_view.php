<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Mengikuti pola v_wallet_month / v_cashflow_month (2026_01_01_001800):
// dashboard/analitik memakai view, bukan query ad-hoc (CLAUDE.md). Dipakai
// AnalyticsActions untuk realisasi per sumber pemasukan (income_sources[] di
// GET /analytics/summary).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            create view v_income_source_month as
            select s.id       as source_id,
                   s.family_id,
                   cast(date_format(t.transaction_date, '%Y-%m-01') as date) as period,
                   coalesce(sum(t.amount), 0)                                as actual
            from income_sources s
            left join transactions t on t.source_id = s.id
                 and t.type = 'income' and t.deleted_at is null
            group by s.id, s.family_id, cast(date_format(t.transaction_date, '%Y-%m-01') as date)
        ");
    }

    public function down(): void
    {
        DB::statement('drop view if exists v_income_source_month');
    }
};
