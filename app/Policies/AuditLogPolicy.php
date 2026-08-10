<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

// Read-only audit trail: never editable or deletable via the API.
class AuditLogPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->belongsToCurrentFamily($auditLog->family_id);
    }
}
