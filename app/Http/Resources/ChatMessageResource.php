<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatMessage */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'thread_id' => $this->thread_id,
            'role' => $this->role,
            'content' => $this->content,
            'input_mode' => $this->input_mode,
            'attachment_url' => $this->attachment_url,
            'token_usage' => $this->token_usage,
            'created_at' => $this->created_at,
        ];
    }
}
