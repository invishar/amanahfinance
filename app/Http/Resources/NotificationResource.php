<?php

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Notification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'member_id' => $this->member_id,
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'deeplink' => $this->deeplink,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
