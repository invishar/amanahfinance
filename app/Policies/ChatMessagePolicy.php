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
    // parent thread's family_id.
    public function view(User $user, ChatMessage $chatMessage): bool
    {
        return $this->belongsToCurrentFamily($chatMessage->thread->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }
}
