<?php

namespace App\Policies;

use App\Models\SubscriptionPlan;
use App\Models\User;

// Platform-wide catalog: index/show are public routes with no gate at all
// (see SubscriptionPlanController), so this policy only covers mutation --
// gated by users.is_platform_admin, never by a family's own admin role
// (CLAUDE.md aturan #7 spirit).
class SubscriptionPlanPolicy
{
    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, SubscriptionPlan $plan): bool
    {
        return $user->isPlatformAdmin();
    }

    public function delete(User $user, SubscriptionPlan $plan): bool
    {
        return $user->isPlatformAdmin();
    }
}
