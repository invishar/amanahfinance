<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WalletBudget */
class WalletBudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            // date-only cast: format explicitly, otherwise default Carbon
            // JSON serialization emits a full UTC datetime and, now that
            // APP_TIMEZONE=Asia/Jakarta, shifts the date back a day.
            'period' => $this->period?->toDateString(),
            'amount' => $this->amount,
            'created_at' => $this->created_at,
        ];
    }
}
