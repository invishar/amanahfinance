<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\IncomeSource */
class IncomeSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'name' => $this->name,
            'owner_member_id' => $this->owner_member_id,
            'expected_amount' => $this->expected_amount,
            'cadence' => $this->cadence,
            'is_archived' => $this->is_archived,
            'created_at' => $this->created_at,
        ];
    }
}
