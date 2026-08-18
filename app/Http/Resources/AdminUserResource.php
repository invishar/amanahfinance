<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subscription = $this->primaryFamilySubscription();

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_admin' => $this->isAdmin(),
            'families_count' => $this->whenCounted('familyMemberships'),
            'subscription_status' => $subscription?->status,
            'subscription_plan_name' => $subscription?->plan?->name,
            'subscription_expires_at' => $subscription?->ends_at,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Langganan family pertama yang diikuti user ini (urut join) -- sekadar
     * ringkasan untuk daftar; detail per-family ada di AdminUserDetailResource.
     * Relasi yang sama persis dipakai AdminUserActions untuk filter
     * `?subscription_status=`, supaya badge dan filter selalu konsisten.
     */
    private function primaryFamilySubscription(): ?Subscription
    {
        return $this->primaryFamilyMembership?->family?->currentSubscription;
    }
}
