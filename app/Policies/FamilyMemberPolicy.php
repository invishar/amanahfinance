<?php

namespace App\Policies;

use App\Models\FamilyMember;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class FamilyMemberPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, FamilyMember $familyMember): bool
    {
        return $this->belongsToCurrentFamily($familyMember->family_id);
    }

    // Managing memberships/roles is an admin action -- a 'member' promoting
    // themselves or others would be a privilege-escalation bug otherwise.
    public function create(User $user): bool
    {
        return $this->roleIn(['admin']);
    }

    public function update(User $user, FamilyMember $familyMember): bool
    {
        return $this->belongsToCurrentFamily($familyMember->family_id)
            && $this->roleIn(['admin']);
    }

    public function delete(User $user, FamilyMember $familyMember): bool
    {
        return $this->belongsToCurrentFamily($familyMember->family_id)
            && $this->roleIn(['admin']);
    }
}
