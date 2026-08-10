<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FamilyInvite */
class FamilyInviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'invited_by' => $this->invited_by,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'token' => $this->token,
            'expires_at' => $this->expires_at,
            'accepted_at' => $this->accepted_at,
            'created_at' => $this->created_at,
        ];
    }
}
