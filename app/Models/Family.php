<?php

namespace App\Models;

use Database\Factories\FamilyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'currency', 'timezone', 'onboarding_done'])]
class Family extends Model
{
    /** @use HasFactory<FamilyFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'onboarding_done' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FamilyMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * @return HasMany<FamilyInvite, $this>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(FamilyInvite::class);
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return HasMany<Wallet, $this>
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * @return HasMany<IncomeSource, $this>
     */
    public function incomeSources(): HasMany
    {
        return $this->hasMany(IncomeSource::class);
    }

    /**
     * @return HasMany<SavingsGoal, $this>
     */
    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<RecurringRule, $this>
     */
    public function recurringRules(): HasMany
    {
        return $this->hasMany(RecurringRule::class);
    }

    /**
     * @return HasMany<ChatThread, $this>
     */
    public function chatThreads(): HasMany
    {
        return $this->hasMany(ChatThread::class);
    }

    /**
     * @return HasMany<AiAction, $this>
     */
    public function aiActions(): HasMany
    {
        return $this->hasMany(AiAction::class);
    }

    /**
     * @return HasMany<OnboardingAnswer, $this>
     */
    public function onboardingAnswers(): HasMany
    {
        return $this->hasMany(OnboardingAnswer::class);
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Baris `subscriptions` terbaru milik family ini, apapun statusnya --
     * dipakai untuk menampilkan status langganan "saat ini" (mis. di admin
     * users), bukan sumber kebenaran transaksional (itu tetap `subscriptions`
     * sendiri, aturan #4).
     *
     * @return HasOne<Subscription, $this>
     */
    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany('created_at');
    }
}
