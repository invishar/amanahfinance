<?php

namespace App\Policies;

use App\Models\IncomeSource;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class IncomeSourcePolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, IncomeSource $incomeSource): bool
    {
        return $this->belongsToCurrentFamily($incomeSource->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    public function update(User $user, IncomeSource $incomeSource): bool
    {
        return $this->belongsToCurrentFamily($incomeSource->family_id)
            && $this->roleIn(['admin', 'member']);
    }

    public function delete(User $user, IncomeSource $incomeSource): bool
    {
        return $this->belongsToCurrentFamily($incomeSource->family_id)
            && $this->roleIn(['admin']);
    }
}
