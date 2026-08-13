<?php

namespace App\Actions\SavingsGoals;

use App\Models\SavingsGoal;
use App\Support\DeletesSafely;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SavingsGoalActions
{
    /**
     * @param  array{status?: string, per_page?: int}  $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = SavingsGoal::query();

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function create(array $data): SavingsGoal
    {
        // fresh(): current_amount/status/created_at have DB-level defaults
        // create() won't reflect when omitted.
        return SavingsGoal::create($data)->fresh();
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
