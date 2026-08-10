<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AiAction */
class AiActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->message_id,
            'family_id' => $this->family_id,
            'action' => $this->action,
            'payload' => $this->payload,
            'status' => $this->status,
            'result_table' => $this->result_table,
            'result_id' => $this->result_id,
            'confidence' => $this->confidence,
            'resolved_at' => $this->resolved_at,
            'resolved_by' => $this->resolved_by,
            'created_at' => $this->created_at,
        ];
    }
}
