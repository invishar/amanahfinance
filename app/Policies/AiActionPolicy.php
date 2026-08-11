<?php

namespace App\Policies;

use App\Models\AiAction;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

// AI never writes business tables directly -- ai_actions rows are only ever
// mutated by ConfirmAiAction, reached through confirm()/reject() here (no
// generic create/update/destroy on this resource).
class AiActionPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, AiAction $aiAction): bool
    {
        return $this->belongsToCurrentFamily($aiAction->family_id);
    }

    public function confirm(User $user, AiAction $aiAction): bool
    {
        return $this->belongsToCurrentFamily($aiAction->family_id)
            && $this->roleIn(['admin', 'member']);
    }

    public function reject(User $user, AiAction $aiAction): bool
    {
        return $this->belongsToCurrentFamily($aiAction->family_id)
            && $this->roleIn(['admin', 'member']);
    }
}
