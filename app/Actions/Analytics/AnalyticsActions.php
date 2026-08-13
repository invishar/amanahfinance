<?php

namespace App\Actions\Analytics;

use App\Models\IncomeSource;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Dashboard & analytics read from v_wallet_month / v_cashflow_month /
// v_income_source_month (aturan CLAUDE.md: "bukan query ad-hoc") -- this
// class only supplements those rows with wallets/sources that have zero
// activity for the period, which the views naturally omit since they only
// ever aggregate matched transaction rows.
class AnalyticsActions
{
    public function summary(string $familyId, Carbon $period): array
    {
        return [
            'period' => $period->toDateString(),
            'cashflow' => $this->cashflow($familyId, $period),
            'wallets' => $this->wallets($familyId, $period),
            'income_sources' => $this->incomeSources($familyId, $period),
        ];
    }

    private function cashflow(string $familyId, Carbon $period): array
    {
        $row = DB::table('v_cashflow_month')
            ->where('family_id', $familyId)
            ->where('period', $period->toDateString())
            ->first();

        $income = (int) ($row->total_income ?? 0);
        $expense = (int) ($row->total_expense ?? 0);
        $savings = (int) ($row->total_savings ?? 0);

        return [
            'total_income' => $income,
            'total_expense' => $expense,
            'total_savings' => $savings,
            'net' => $income - $expense,
        ];
    }

    private function wallets(string $familyId, Carbon $period): array
    {
        $rows = DB::table('v_wallet_month')
            ->where('family_id', $familyId)
            ->where('period', $period->toDateString())
            ->get()
            ->keyBy('wallet_id');

        // v_wallet_month's own wallet_budgets join is hardcoded to curdate(),
        // not the queried period, and its period column comes from the
        // *joined transaction's* date -- so a wallet with zero transactions
        // this month gets no row at all, and the override would silently be
        // lost. Mirror the view's own curdate()-based lookup here so a
        // wallet's budget doesn't depend on whether it happened to have any
        // spending yet.
        $currentMonthBudgets = DB::table('wallet_budgets')
            ->where('period', now()->startOfMonth()->toDateString())
            ->pluck('amount', 'wallet_id');

        return Wallet::query()
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get()
            ->map(function (Wallet $wallet) use ($rows, $currentMonthBudgets) {
                $row = $rows->get($wallet->id);
                $budget = $row
                    ? (int) $row->budget
                    : (int) ($currentMonthBudgets->get($wallet->id) ?? $wallet->monthly_budget);
                $spent = $row ? (int) $row->spent : 0;

                return [
                    'wallet_id' => $wallet->id,
                    'name' => $wallet->name,
                    'icon' => $wallet->icon,
                    'color' => $wallet->color,
                    'budget' => $budget,
                    'spent' => $spent,
                    'remaining' => $budget - $spent,
                    'percent' => $budget > 0 ? (int) round(min($spent, $budget) / $budget * 100) : 0,
                    'status' => $this->status($spent, $budget),
                ];
            })
            ->values()
            ->all();
    }

    private function incomeSources(string $familyId, Carbon $period): array
    {
        $rows = DB::table('v_income_source_month')
            ->where('family_id', $familyId)
            ->where('period', $period->toDateString())
            ->get()
            ->keyBy('source_id');

        return IncomeSource::query()
            ->where('is_archived', false)
            ->orderBy('name')
            ->get()
            ->map(fn (IncomeSource $source) => [
                'source_id' => $source->id,
                'name' => $source->name,
                'expected' => $source->expected_amount,
                'actual' => (int) ($rows->get($source->id)->actual ?? 0),
            ])
            ->values()
            ->all();
    }

    private function status(int $spent, int $budget): string
    {
        if ($budget <= 0) {
            return 'no_budget';
        }

        $percent = $spent / $budget * 100;

        return match (true) {
            $percent >= 100 => 'over',
            $percent >= 80 => 'warning',
            default => 'ok',
        };
    }
}
