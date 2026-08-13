<?php

namespace App\Actions\SubscriptionPlans;

use App\Models\SubscriptionPlan;
use App\Support\DeletesSafely;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionPlanActions
{
    /**
     * @param  array{is_active?: bool}  $filters
     * @return Collection<int, SubscriptionPlan>
     */
    public function index(array $filters): Collection
    {
        $query = SubscriptionPlan::query();

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('price')->get();
    }

    public function create(array $data): SubscriptionPlan
    {
        return SubscriptionPlan::create($data)->fresh();
    }

    public function update(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        $plan->update($data);

        return $plan->fresh();
    }

    public function delete(SubscriptionPlan $plan): void
    {
        DeletesSafely::run(
            fn () => $plan->delete(),
            'Paket ini masih direferensikan oleh langganan yang ada. Nonaktifkan (is_active=false) alih-alih menghapus.',
        );
    }
}
