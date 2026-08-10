<?php

namespace App\Policies;

use App\Models\FamilyInvite;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class FamilyInvitePolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, FamilyInvite $familyInvite): bool
    {
        return $this->belongsToCurrentFamily($familyInvite->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin']);
    }

    public function update(User $user, FamilyInvite $familyInvite): bool
    {
        return $this->belongsToCurrentFamily($familyInvite->family_id)
            && $this->roleIn(['admin']);
    }

    public function delete(User $user, FamilyInvite $familyInvite): bool
    {
        return $this->belongsToCurrentFamily($familyInvite->family_id)
            && $this->roleIn(['admin']);
    }
}
