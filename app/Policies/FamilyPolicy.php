<?php

namespace App\Policies;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;

class FamilyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Family $family): bool
    {
        return $this->membership($user, $family) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Family $family): bool
    {
        return $this->membership($user, $family)?->role === 'admin';
    }

    public function delete(User $user, Family $family): bool
    {
        return $this->membership($user, $family)?->role === 'admin';
    }

    private function membership(User $user, Family $family): ?FamilyMember
    {
        return FamilyMember::query()
            ->where('family_id', $family->id)
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->first();
    }
}
