<?php

namespace App\Http\Resources;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wallet */
class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'monthly_budget' => $this->monthly_budget,
            'rollover' => $this->rollover,
            'is_archived' => $this->is_archived,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
        ];
    }
}
