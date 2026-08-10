<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['family_id', 'user_id', 'role', 'nickname', 'monthly_quota', 'joined_at', 'removed_at'])]
class FamilyMember extends Model
{
    /** @use HasFactory<\Database\Factories\FamilyMemberFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'removed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function ownedAccounts(): HasMany
    {
        return $this->hasMany(Account::class, 'owner_member_id');
    }

    /**
     * @return HasMany<IncomeSource, $this>
     */
    public function ownedIncomeSources(): HasMany
    {
        return $this->hasMany(IncomeSource::class, 'owner_member_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function createdTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'created_by');
    }

    /**
     * @return HasMany<ChatThread, $this>
     */
    public function chatThreads(): HasMany
    {
        return $this->hasMany(ChatThread::class, 'member_id');
    }

    /**
     * @return HasMany<AiAction, $this>
     */
    public function resolvedAiActions(): HasMany
    {
        return $this->hasMany(AiAction::class, 'resolved_by');
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'member_id');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
