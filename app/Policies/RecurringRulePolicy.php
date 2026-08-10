<?php

namespace App\Policies;

use App\Models\RecurringRule;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class RecurringRulePolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, RecurringRule $recurringRule): bool
    {
        return $this->belongsToCurrentFamily($recurringRule->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    public function update(User $user, RecurringRule $recurringRule): bool
    {
        return $this->belongsToCurrentFamily($recurringRule->family_id)
            && $this->roleIn(['admin', 'member']);
    }

    public function delete(User $user, RecurringRule $recurringRule): bool
    {
        return $this->belongsToCurrentFamily($recurringRule->family_id)
            && $this->roleIn(['admin']);
    }
}
