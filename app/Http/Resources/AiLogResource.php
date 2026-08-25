<?php

namespace App\Http\Resources;

use App\Models\AiLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiLog */
class AiLogResource extends JsonResource
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
            'user_prompt' => $this->user_prompt,
            'system_prompt' => $this->system_prompt,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'created_at' => $this->created_at,
        ];
    }
}
