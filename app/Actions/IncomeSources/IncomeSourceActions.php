<?php

namespace App\Actions\IncomeSources;

use App\Models\IncomeSource;
use App\Support\DeletesSafely;

class IncomeSourceActions
{
    public function create(array $data): IncomeSource
    {
        return IncomeSource::create($data);
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
