<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminUserDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_admin' => $this->isAdmin(),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'families' => $this->familyMemberships->map(fn ($membership) => [
                'family_id' => $membership->family_id,
                'family_name' => $membership->family->name,
                'role' => $membership->role,
                'joined_at' => $membership->joined_at,
            ]),
        ];
    }
}
