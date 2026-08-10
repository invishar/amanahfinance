<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SavingsGoal */
class SavingsGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'target_name' => $this->target_name,
            'target_amount' => $this->target_amount,
            'current_amount' => $this->current_amount,
            'percent' => $this->target_amount > 0
                ? (int) round(min($this->current_amount, $this->target_amount) / $this->target_amount * 100)
                : 0,
            'deadline' => $this->deadline?->toDateString(),
            'icon' => $this->icon,
            'color' => $this->color,
            'account_id' => $this->account_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'achieved_at' => $this->achieved_at,
        ];
    }
}
