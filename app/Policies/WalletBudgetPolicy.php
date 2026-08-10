<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WalletBudget;
use App\Policies\Concerns\AuthorizesWithinFamily;

class WalletBudgetPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    // WalletBudget has no family_id column of its own -- tenant isolation for
    // this resource relies entirely on the parent wallet's family_id.
    public function view(User $user, WalletBudget $walletBudget): bool
    {
        return $this->belongsToCurrentFamily($walletBudget->wallet->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    public function update(User $user, WalletBudget $walletBudget): bool
    {
        return $this->belongsToCurrentFamily($walletBudget->wallet->family_id)
            && $this->roleIn(['admin', 'member']);
    }

    public function delete(User $user, WalletBudget $walletBudget): bool
    {
        return $this->belongsToCurrentFamily($walletBudget->wallet->family_id)
            && $this->roleIn(['admin']);
    }
}
