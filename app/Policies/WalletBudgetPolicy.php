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
        return $this->belongsToCurrentFamily($this->walletFamilyId($walletBudget));
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    public function update(User $user, WalletBudget $walletBudget): bool
    {
        return $this->belongsToCurrentFamily($this->walletFamilyId($walletBudget))
            && $this->roleIn(['admin', 'member']);
    }

    public function delete(User $user, WalletBudget $walletBudget): bool
    {
        return $this->belongsToCurrentFamily($this->walletFamilyId($walletBudget))
            && $this->roleIn(['admin']);
    }

    // The wallet relation is itself family-scoped, so a wallet belonging to a
    // *different* family than the current one would resolve to null through
    // the normal relation -- exactly the case this check needs to detect.
    private function walletFamilyId(WalletBudget $walletBudget): ?string
    {
        return $walletBudget->wallet()->withoutGlobalScope('family')->value('family_id');
    }
}
