<?php

namespace App\Http\Resources;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Account */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'name' => $this->name,
            'account_type' => $this->account_type,
            'institution' => $this->institution,
            'masked_number' => $this->masked_number,
            'opening_balance' => $this->opening_balance,
            'current_balance' => $this->current_balance,
            'owner_member_id' => $this->owner_member_id,
            'is_shared' => $this->is_shared,
            'is_archived' => $this->is_archived,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
        ];
    }
}
