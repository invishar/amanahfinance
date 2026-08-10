<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChatThread */
class ChatThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'member_id' => $this->member_id,
            'title' => $this->title,
            'kind' => $this->kind,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,
        ];
    }
}
