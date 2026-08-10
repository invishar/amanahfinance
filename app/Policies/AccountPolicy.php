<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class AccountPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, Account $account): bool
    {
        return $this->belongsToCurrentFamily($account->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    public function update(User $user, Account $account): bool
    {
        return $this->belongsToCurrentFamily($account->family_id)
            && $this->roleIn(['admin', 'member']);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->belongsToCurrentFamily($account->family_id)
            && $this->roleIn(['admin']);
    }
}
