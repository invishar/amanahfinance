<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class TransactionPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->belongsToCurrentFamily($transaction->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->belongsToCurrentFamily($transaction->family_id)
            && $this->roleIn(['admin', 'member']);
    }

    // Recording money is everyday member usage, not an administrative act --
    // unlike other resources, delete here is not admin-only so members can
    // fix their own entry mistakes. It's a soft delete either way.
    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->belongsToCurrentFamily($transaction->family_id)
            && $this->roleIn(['admin', 'member']);
    }
}
