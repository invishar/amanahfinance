<?php

namespace App\Actions\Notifications;

use App\Models\Notification;

class NotificationActions
{
    public function create(array $data): Notification
    {
        // created_at is DB useCurrent(), not set by create() itself.
        return Notification::create($data)->fresh();
    }

    public function update(Notification $notification, array $data): Notification
    {
        $notification->update($data);

        return $notification->fresh();
    }

    public function delete(Notification $notification): void
    {
        $notification->delete();
    }
}
