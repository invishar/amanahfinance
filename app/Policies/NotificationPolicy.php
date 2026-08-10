<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinFamily;

class NotificationPolicy
{
    use AuthorizesWithinFamily;

    public function viewAny(User $user): bool
    {
        return $this->currentFamilyId() !== null;
    }

    public function view(User $user, Notification $notification): bool
    {
        return $this->belongsToCurrentFamily($notification->family_id);
    }

    public function create(User $user): bool
    {
        return $this->roleIn(['admin']);
    }

    // Members mark their own notifications read/unread.
    public function update(User $user, Notification $notification): bool
    {
        return $this->belongsToCurrentFamily($notification->family_id)
            && $this->roleIn(['admin', 'member', 'viewer']);
    }

    public function delete(User $user, Notification $notification): bool
    {
        return $this->belongsToCurrentFamily($notification->family_id)
            && $this->roleIn(['admin', 'member', 'viewer']);
    }
}
