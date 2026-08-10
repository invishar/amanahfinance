<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Transaction */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'account_id' => $this->account_id,
            'to_account_id' => $this->to_account_id,
            'wallet_id' => $this->wallet_id,
            'source_id' => $this->source_id,
            'goal_id' => $this->goal_id,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'origin' => $this->origin,
            'receipt_url' => $this->receipt_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
