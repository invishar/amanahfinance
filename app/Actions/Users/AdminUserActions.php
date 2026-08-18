<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminUserActions
{
    /**
     * @param  array{search?: string, subscription_status?: string, per_page?: int}  $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->withCount('familyMemberships')
            ->with('primaryFamilyMembership.family.currentSubscription.plan')
            ->orderByDesc('created_at');

        if (filled($filters['search'] ?? null)) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        // Sama seperti yang ditampilkan AdminUserResource: status langganan
        // TERBARU family PERTAMA yang diikuti user (bukan cek semua family
        // user, supaya filter dan badge di daftar selalu konsisten).
        if (filled($filters['subscription_status'] ?? null)) {
            $status = $filters['subscription_status'];

            if ($status === 'none') {
                $query->whereDoesntHave('primaryFamilyMembership.family.currentSubscription');
            } else {
                $query->whereHas(
                    'primaryFamilyMembership.family.currentSubscription',
                    fn ($q) => $q->where('status', $status)
                );
            }
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function show(User $user): User
    {
        return $user->load('familyMemberships.family.currentSubscription.plan');
    }
}
