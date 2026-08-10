<?php

namespace App\Policies;

use App\Models\AiAction;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

// Read-only: AI never writes business tables directly, and ai_actions rows
// are only ever mutated by ConfirmAiAction -- no create/update/delete here.
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
}
