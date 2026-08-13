<?php

namespace App\Http\Resources;

use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FamilyMember */
class FamilyMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'nickname' => $this->nickname,
            'monthly_quota' => $this->monthly_quota,
            'joined_at' => $this->joined_at,
            'removed_at' => $this->removed_at,
            'user' => new UserSummaryResource($this->whenLoaded('user')),
        ];
    }
}
