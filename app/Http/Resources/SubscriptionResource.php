<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'plan_id' => $this->plan_id,
            'plan_code' => $this->plan->code,
            'plan_name' => $this->plan->name,
            'status' => $this->status,
            'amount' => $this->amount,
            'payment_note' => $this->payment_note,
            'payment_proof_url' => $this->payment_proof_url,
            'paid_at' => $this->paid_at,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'requested_by' => $this->requested_by,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at,
            'review_note' => $this->review_note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
