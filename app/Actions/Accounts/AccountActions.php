<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Support\DeletesSafely;

class AccountActions
{
    public function create(array $data): Account
    {
        $opening = $data['opening_balance'] ?? 0;

        $account = Account::create([
            ...$data,
            'opening_balance' => $opening,
            'current_balance' => $opening,
        ]);

        // fresh(): is_shared/is_archived/sort_order/created_at have DB-level
        // defaults that create() doesn't reflect back when omitted.
        return $account->fresh();
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($data);

        return $account->fresh();
    }

    public function delete(Account $account): void
    {
        DeletesSafely::run(
            fn () => $account->delete(),
            'Akun ini masih dipakai oleh transaksi yang ada. Arsipkan alih-alih menghapus.',
        );
    }
}
