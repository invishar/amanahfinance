<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class SubscriptionPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->isPlatformAdmin() || $this->belongsToCurrentFamily($subscription->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    // Admin review surface: cross-family by design (a platform admin reviews
    // every family's requests), so these are gated purely by is_platform_admin,
    // never by family role -- mirrors LlmSettingPolicy, not SavingsGoalPolicy.
    public function reviewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function activate(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function reject(User $user): bool
    {
        return $user->isPlatformAdmin();
    }
}
