<?php

namespace App\Policies;

use App\Models\User;

// Platform admin's own user directory -- gated by users.is_admin, same as
// LlmSettingPolicy/SubscriptionPlanPolicy. Deliberately read-only: granting
// or revoking is_admin itself stays tinker-only (see User model docblock),
// so there is no update() here at all.
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin();
    }
}
