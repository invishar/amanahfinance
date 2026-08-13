<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'actor_id' => $this->actor_id,
            'entity' => $this->entity,
            'entity_id' => $this->entity_id,
            'action' => $this->action,
            'diff' => $this->diff,
            'created_at' => $this->created_at,
        ];
    }
}
