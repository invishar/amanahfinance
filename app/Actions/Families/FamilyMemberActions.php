<?php

namespace App\Actions\Families;

use App\Models\FamilyMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FamilyMemberActions
{
    /**
     * @param  array{role?: string, per_page?: int}  $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = FamilyMember::query()->with('user');

        if (filled($filters['role'] ?? null)) {
            $query->where('role', $filters['role']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function create(array $data): FamilyMember
    {
        return FamilyMember::create([...$data, 'joined_at' => now()]);
    }

    public function update(FamilyMember $familyMember, array $data): FamilyMember
    {
        $familyMember->update($data);

        return $familyMember->fresh();
    }

    // Members are never hard-deleted: removed_at is the source of truth for
    // "active" membership (see family_members_active_idx), keeping history intact.
    public function delete(FamilyMember $familyMember): void
    {
        $familyMember->update(['removed_at' => now()]);
    }
}
