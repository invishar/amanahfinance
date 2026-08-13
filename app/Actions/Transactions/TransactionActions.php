<?php

namespace App\Actions\Transactions;

use App\Models\Account;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Support\CurrentFamily;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// transactions is the source of truth (aturan #4): accounts.current_balance
// and savings_goals.current_amount are caches that only ever move inside the
// same DB transaction as the ledger row itself.
class TransactionActions
{
    /**
     * @param  array{month?: string, wallet_id?: string, account_id?: string, type?: string, per_page?: int}  $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = Transaction::query();

        if (filled($filters['month'] ?? null)) {
            $period = Carbon::createFromFormat('Y-m', $filters['month']);
            $query->whereBetween('transaction_date', [
                $period->copy()->startOfMonth()->toDateString(),
                $period->copy()->endOfMonth()->toDateString(),
            ]);
        }

        if (filled($filters['wallet_id'] ?? null)) {
            $query->where('wallet_id', $filters['wallet_id']);
        }

        if (filled($filters['account_id'] ?? null)) {
            $query->where('account_id', $filters['account_id']);
        }

        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }

        return $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function create(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $data = $this->normalizeByType($data);

            $transaction = Transaction::create([
                ...$data,
                'origin' => $data['origin'] ?? 'manual',
                'created_by' => app(CurrentFamily::class)->memberId(),
            ]);

            $this->applyEffect($transaction);

            return $transaction;
        });
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $this->reverseEffect($transaction);

            $merged = $this->normalizeByType(array_merge($transaction->only([
                'type', 'amount', 'transaction_date', 'account_id', 'to_account_id',
                'wallet_id', 'source_id', 'goal_id', 'note', 'receipt_url',
            ]), $data));

            $transaction->update($merged);
            $transaction->refresh();

            $this->applyEffect($transaction);

            return $transaction;
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $this->reverseEffect($transaction);
            $transaction->delete();
        });
    }

    // Only the foreign key relevant to the (possibly changed) type survives;
    // the others are cleared so stale references never linger.
    private function normalizeByType(array $data): array
    {
        $type = $data['type'];

        $data['wallet_id'] = $type === 'expense' ? ($data['wallet_id'] ?? null) : null;
        $data['source_id'] = $type === 'income' ? ($data['source_id'] ?? null) : null;
        $data['goal_id'] = $type === 'savings' ? ($data['goal_id'] ?? null) : null;
        $data['to_account_id'] = $type === 'transfer' ? ($data['to_account_id'] ?? null) : null;

        return $data;
    }

    private function applyEffect(Transaction $transaction): void
    {
        match ($transaction->type) {
            'expense' => $this->adjustAccount($transaction->account_id, -$transaction->amount),
            'income' => $this->adjustAccount($transaction->account_id, $transaction->amount),
            'transfer' => $this->applyTransfer($transaction, 1),
            'savings' => $this->applySavings($transaction, 1),
        };
    }

    private function reverseEffect(Transaction $transaction): void
    {
        match ($transaction->type) {
            'expense' => $this->adjustAccount($transaction->account_id, $transaction->amount),
            'income' => $this->adjustAccount($transaction->account_id, -$transaction->amount),
            'transfer' => $this->applyTransfer($transaction, -1),
            'savings' => $this->applySavings($transaction, -1),
        };
    }

    private function applyTransfer(Transaction $transaction, int $sign): void
    {
        $this->adjustAccount($transaction->account_id, -$sign * $transaction->amount);
        $this->adjustAccount($transaction->to_account_id, $sign * $transaction->amount);
    }

    private function applySavings(Transaction $transaction, int $sign): void
    {
        $this->adjustAccount($transaction->account_id, -$sign * $transaction->amount);
        $this->adjustGoal($transaction->goal_id, $sign * $transaction->amount);
    }

    private function adjustAccount(?string $accountId, int $delta): void
    {
        if ($accountId !== null) {
            Account::whereKey($accountId)->increment('current_balance', $delta);
        }
    }

    private function adjustGoal(?string $goalId, int $delta): void
    {
        if ($goalId !== null) {
            SavingsGoal::whereKey($goalId)->increment('current_amount', $delta);
        }
    }
}
