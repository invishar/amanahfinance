<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notifications\NotificationActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Requests\UpdateNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function __construct(private NotificationActions $actions) {}

    public function index()
    {
        $this->authorize('viewAny', Notification::class);

        return NotificationResource::collection(
            Notification::query()->orderByDesc('created_at')->paginate(20)
        );
    }

    public function store(StoreNotificationRequest $request)
    {
        $notification = $this->actions->create($request->validated());

        return (new NotificationResource($notification))->response()->setStatusCode(201);
    }

    public function show(Notification $notification)
    {
        $this->authorize('view', $notification);

        return new NotificationResource($notification);
    }

    public function update(UpdateNotificationRequest $request, Notification $notification)
    {
        $notification = $this->actions->update($notification, $request->validated());

        return new NotificationResource($notification);
    }

    public function destroy(Notification $notification)
    {
        $this->authorize('delete', $notification);

        $this->actions->delete($notification);

        return response()->json(null, 204);
    }
}
