<?php

namespace App\Policies;

use App\Models\OnboardingAnswer;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class OnboardingAnswerPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, OnboardingAnswer $onboardingAnswer): bool
    {
        return $this->belongsToCurrentFamily($onboardingAnswer->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    public function update(User $user, OnboardingAnswer $onboardingAnswer): bool
    {
        return $this->belongsToCurrentFamily($onboardingAnswer->family_id)
            && $this->roleIn(['admin', 'member']);
    }

    public function delete(User $user, OnboardingAnswer $onboardingAnswer): bool
    {
        return $this->belongsToCurrentFamily($onboardingAnswer->family_id)
            && $this->roleIn(['admin']);
    }
}
