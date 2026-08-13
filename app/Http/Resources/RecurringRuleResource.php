<?php

namespace App\Http\Resources;

use App\Models\RecurringRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringRule */
class RecurringRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'wallet_id' => $this->wallet_id,
            'source_id' => $this->source_id,
            'account_id' => $this->account_id,
            'note' => $this->note,
            'rrule' => $this->rrule,
            'next_run_on' => $this->next_run_on?->toDateString(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
