<?php

namespace App\Policies;

use App\Models\SavingsGoal;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class SavingsGoalPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, SavingsGoal $savingsGoal): bool
    {
        return $this->belongsToCurrentFamily($savingsGoal->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    public function update(User $user, SavingsGoal $savingsGoal): bool
    {
        return $this->belongsToCurrentFamily($savingsGoal->family_id)
            && $this->roleIn(['admin', 'member']);
    }

    public function delete(User $user, SavingsGoal $savingsGoal): bool
    {
        return $this->belongsToCurrentFamily($savingsGoal->family_id)
            && $this->roleIn(['admin']);
    }
}
