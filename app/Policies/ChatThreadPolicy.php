<?php

namespace App\Policies;

use App\Models\ChatThread;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class ChatThreadPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, ChatThread $chatThread): bool
    {
        return $this->belongsToCurrentFamily($chatThread->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin', 'member']);
    }

    public function update(User $user, ChatThread $chatThread): bool
    {
        return $this->belongsToCurrentFamily($chatThread->family_id)
            && $this->roleIn(['admin', 'member']);
    }

    public function delete(User $user, ChatThread $chatThread): bool
    {
        return $this->belongsToCurrentFamily($chatThread->family_id)
            && $this->roleIn(['admin', 'member']);
    }
}
