<?php

namespace App\Http\Resources;

use App\Models\AiProviderError;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiProviderError */
class AiProviderErrorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'family_name' => $this->whenLoaded('family', fn () => $this->family?->name),
            'thread_id' => $this->thread_id,
            'message_id' => $this->message_id,
            'model' => $this->model,
            'status' => $this->status,
            'exception' => $this->exception,
            'body' => $this->body,
            'created_at' => $this->created_at,
        ];
    }
}
