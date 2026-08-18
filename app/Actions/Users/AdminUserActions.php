<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminUserActions
{
    /**
     * @param  array{search?: string, per_page?: int}  $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->withCount('familyMemberships')
            ->orderByDesc('created_at');

        if (filled($filters['search'] ?? null)) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function show(User $user): User
    {
        return $user->load('familyMemberships.family');
    }
}
