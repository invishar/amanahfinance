<?php

namespace App\Actions\Wallets;

use App\Models\Wallet;
use App\Support\DeletesSafely;

class WalletActions
{
    public function create(array $data): Wallet
    {
        return Wallet::create($data);
    }

    public function update(Wallet $wallet, array $data): Wallet
    {
        $wallet->update($data);

        return $wallet->fresh();
    }

    public function delete(Wallet $wallet): void
    {
        DeletesSafely::run(
            fn () => $wallet->delete(),
            'Wallet ini masih dipakai oleh transaksi yang ada. Arsipkan alih-alih menghapus.',
        );
    }
}
