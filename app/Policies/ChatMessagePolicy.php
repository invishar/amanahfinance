<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class ChatMessagePolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    // ChatMessage has no family_id of its own -- isolation relies on the
    // parent thread's family_id. The thread relation is itself family-scoped,
    // so it must be read without that scope here, otherwise a message whose
    // thread belongs to a *different* family resolves to null -- exactly the
    // case this check needs to detect.
    public function view(User $user, ChatMessage $chatMessage): bool
    {
        $familyId = $chatMessage->thread()->withoutGlobalScope('family')->value('family_id');

        return $this->belongsToCurrentFamily($familyId);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }
}
