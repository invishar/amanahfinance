<?php

namespace App\Actions\Wallets;

use App\Models\Wallet;
use App\Models\WalletBudget;
use Illuminate\Support\Carbon;

class WalletBudgetActions
{
    public function create(Wallet $wallet, array $data): WalletBudget
    {
        return $wallet->budgets()->create([
            ...$data,
            'period' => $this->firstOfMonth($data['period']),
        ]);
    }

    public function update(WalletBudget $walletBudget, array $data): WalletBudget
    {
        if (isset($data['period'])) {
            $data['period'] = $this->firstOfMonth($data['period']);
        }

        $walletBudget->update($data);

        return $walletBudget->fresh();
    }

    public function delete(WalletBudget $walletBudget): void
    {
        $walletBudget->delete();
    }

    // period selalu tanggal 1 bulan ybs (lihat komentar migrasi wallet_budgets).
    private function firstOfMonth(string $period): string
    {
        return Carbon::parse($period)->startOfMonth()->toDateString();
    }
}
