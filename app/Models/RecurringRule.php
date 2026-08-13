<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Database\Factories\RecurringRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'family_id', 'type', 'amount', 'wallet_id', 'source_id', 'account_id',
    'note', 'rrule', 'next_run_on', 'is_active',
])]
class RecurringRule extends Model
{
    /** @use HasFactory<RecurringRuleFactory> */
    use BelongsToFamily, HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'next_run_on' => 'date',
            'is_active' => 'boolean',
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
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<IncomeSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(IncomeSource::class, 'source_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
