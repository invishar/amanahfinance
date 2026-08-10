<?php

namespace App\Actions\SavingsGoals;

use App\Models\SavingsGoal;
use App\Support\DeletesSafely;

class SavingsGoalActions
{
    public function create(array $data): SavingsGoal
    {
        return SavingsGoal::create($data);
    }

    public function update(SavingsGoal $savingsGoal, array $data): SavingsGoal
    {
        if (array_key_exists('status', $data)) {
            $data['achieved_at'] = $data['status'] === 'achieved' ? now() : null;
        }

        $savingsGoal->update($data);

        return $savingsGoal->fresh();
    }

    public function delete(SavingsGoal $savingsGoal): void
    {
        DeletesSafely::run(
            fn () => $savingsGoal->delete(),
            'Target tabungan ini masih dipakai oleh transaksi yang ada. Batalkan (status=cancelled) alih-alih menghapus.',
        );
    }
}
