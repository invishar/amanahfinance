<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'family_id', 'name', 'account_type', 'institution', 'masked_number',
    'opening_balance', 'current_balance', 'owner_member_id', 'is_shared',
    'is_archived', 'sort_order',
])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use BelongsToFamily, HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'opening_balance' => 'integer',
            'current_balance' => 'integer',
            'is_shared' => 'boolean',
            'is_archived' => 'boolean',
            'created_at' => 'datetime',
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
     * @return BelongsTo<FamilyMember, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'owner_member_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_account_id');
    }

    /**
     * @return HasMany<SavingsGoal, $this>
     */
    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    /**
     * @return HasMany<RecurringRule, $this>
     */
    public function recurringRules(): HasMany
    {
        return $this->hasMany(RecurringRule::class);
    }
}
