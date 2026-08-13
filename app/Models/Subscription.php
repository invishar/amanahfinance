<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'family_id', 'plan_id', 'status', 'amount', 'payment_note', 'payment_proof_url', 'paid_at',
    'starts_at', 'ends_at', 'requested_by', 'reviewed_by', 'reviewed_at', 'review_note',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToFamily, HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
