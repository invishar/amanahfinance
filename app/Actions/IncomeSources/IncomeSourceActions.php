<?php

namespace App\Actions\IncomeSources;

use App\Models\IncomeSource;
use App\Support\DeletesSafely;

class IncomeSourceActions
{
    public function create(array $data): IncomeSource
    {
        // fresh(): is_archived/created_at have DB-level defaults create()
        // won't reflect when omitted.
        return IncomeSource::create($data)->fresh();
    }

    public function update(IncomeSource $incomeSource, array $data): IncomeSource
    {
        $incomeSource->update($data);

        return $incomeSource->fresh();
    }

    public function delete(IncomeSource $incomeSource): void
    {
        DeletesSafely::run(
            fn () => $incomeSource->delete(),
            'Sumber pemasukan ini masih dipakai oleh transaksi yang ada. Arsipkan alih-alih menghapus.',
        );
    }
}
