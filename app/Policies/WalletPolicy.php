<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;
use App\Policies\Concerns\AuthorizesWithinFamily;

class WalletPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, Wallet $wallet): bool
    {
        return $this->belongsToCurrentFamily($wallet->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    public function update(User $user, Wallet $wallet): bool
    {
        return $this->belongsToCurrentFamily($wallet->family_id)
            && $this->roleIn(['admin', 'member']);
    }

    public function delete(User $user, Wallet $wallet): bool
    {
        return $this->belongsToCurrentFamily($wallet->family_id)
            && $this->roleIn(['admin']);
    }
}
