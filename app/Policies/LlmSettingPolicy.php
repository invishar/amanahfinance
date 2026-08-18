<?php

namespace App\Policies;

use App\Models\User;

// Platform-wide, not family-scoped. Gated by users.is_admin, not by
// any family_members.role -- a family's own admin must never manage the
// credentials the whole platform runs on (aturan #7).
class LlmSettingPolicy
{
    public function view(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
}
