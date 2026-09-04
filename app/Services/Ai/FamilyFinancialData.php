<?php

namespace App\Services\Ai;

use App\Actions\Analytics\AnalyticsActions;
use App\Models\Account;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\IncomeSource;
use App\Models\OnboardingAnswer;
use App\Models\RecurringRule;
use App\Models\SavingsGoal;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Carbon;

/**
 * Read-only data gateway for Amina.
 *
 * Every root query has an explicit family_id constraint because queue jobs do
 * not run through ResolveFamily. Never rely on BelongsToFamily's global scope
 * here: it deliberately does nothing when CurrentFamily has not been set.
 */
class FamilyFinancialData
{
    public function __construct(private AnalyticsActions $analytics) {}

    public function read(Family $family, string $topic, array $filters = []): array
    {
        return match ($topic) {
            'summary' => $this->summary($family, $filters),
            'accounts' => $this->accounts($family),
            'savings_goals' => $this->savingsGoals($family),
            'recent_transactions' => $this->transactions($family, $filters),
            'recurring_rules' => $this->recurringRules($family),
            'subscription' => $this->subscription($family),
            'family_profile' => $this->familyProfile($family),
            default => ['error' => 'Topik data tidak dikenal.'],
        };
    }

    private function summary(Family $family, array $filters): array
    {
        $period = filled($filters['month'] ?? null)
            ? Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth()
            : now()->startOfMonth();

        return $this->analytics->summary($family->id, $period);
    }

    private function accounts(Family $family): array
    {
        return Account::query()
            ->where('family_id', $family->id)
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get(['name', 'account_type', 'institution', 'current_balance', 'is_shared'])
            ->map(fn (Account $account) => [
                'name' => $account->name,
                'type' => $account->account_type,
                'institution' => $account->institution,
                'balance' => $account->current_balance,
                'shared' => $account->is_shared,
            ])->all();
    }

    private function savingsGoals(Family $family): array
    {
        $accounts = Account::query()
            ->where('family_id', $family->id)
            ->pluck('name', 'id');

        return SavingsGoal::query()
            ->where('family_id', $family->id)
            ->orderByRaw("status = 'active' desc")
            ->orderBy('deadline')
            ->get(['target_name', 'target_amount', 'current_amount', 'deadline', 'account_id', 'status'])
            ->map(function (SavingsGoal $goal) use ($accounts) {
                $remaining = max(0, $goal->target_amount - $goal->current_amount);

                return [
                    'name' => $goal->target_name,
                    'target' => $goal->target_amount,
                    'current' => $goal->current_amount,
                    'remaining' => $remaining,
                    'percent' => $goal->target_amount > 0
                        ? (int) round(min($goal->current_amount, $goal->target_amount) / $goal->target_amount * 100)
                        : 0,
                    'deadline' => $goal->deadline?->toDateString(),
                    'account' => $accounts->get($goal->account_id),
                    'status' => $goal->status,
                ];
            })->all();
    }

    private function transactions(Family $family, array $filters): array
    {
        $limit = min(max((int) ($filters['limit'] ?? 20), 1), 50);
        $query = Transaction::query()
            ->where('family_id', $family->id);

        if (filled($filters['month'] ?? null)) {
            $period = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();
            $query->whereBetween('transaction_date', [
                $period->toDateString(),
                $period->copy()->endOfMonth()->toDateString(),
            ]);
        }

        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }

        $accountNames = Account::query()->where('family_id', $family->id)->pluck('name', 'id');
        $walletNames = Wallet::query()->where('family_id', $family->id)->pluck('name', 'id');
        $sourceNames = IncomeSource::query()->where('family_id', $family->id)->pluck('name', 'id');
        $goalNames = SavingsGoal::query()->where('family_id', $family->id)->pluck('target_name', 'id');

        return $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['type', 'amount', 'transaction_date', 'account_id', 'to_account_id', 'wallet_id', 'source_id', 'goal_id', 'note', 'origin'])
            ->map(fn (Transaction $transaction) => [
                'date' => $transaction->transaction_date->toDateString(),
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'account' => $accountNames->get($transaction->account_id),
                'to_account' => $accountNames->get($transaction->to_account_id),
                'wallet' => $walletNames->get($transaction->wallet_id),
                'income_source' => $sourceNames->get($transaction->source_id),
                'savings_goal' => $goalNames->get($transaction->goal_id),
                'note' => $transaction->note,
                'origin' => $transaction->origin,
            ])->all();
    }

    private function recurringRules(Family $family): array
    {
        $accountNames = Account::query()->where('family_id', $family->id)->pluck('name', 'id');
        $walletNames = Wallet::query()->where('family_id', $family->id)->pluck('name', 'id');
        $sourceNames = IncomeSource::query()->where('family_id', $family->id)->pluck('name', 'id');

        return RecurringRule::query()
            ->where('family_id', $family->id)
            ->where('is_active', true)
            ->orderBy('next_run_on')
            ->get(['type', 'amount', 'account_id', 'wallet_id', 'source_id', 'note', 'rrule', 'next_run_on'])
            ->map(fn (RecurringRule $rule) => [
                'type' => $rule->type,
                'amount' => $rule->amount,
                'account' => $accountNames->get($rule->account_id),
                'wallet' => $walletNames->get($rule->wallet_id),
                'income_source' => $sourceNames->get($rule->source_id),
                'note' => $rule->note,
                'schedule' => $rule->rrule,
                'next_run_on' => $rule->next_run_on->toDateString(),
            ])->all();
    }

    private function subscription(Family $family): array
    {
        $subscription = Subscription::query()
            ->where('family_id', $family->id)
            ->latest('created_at')
            ->first(['plan_id', 'status', 'amount', 'paid_at', 'starts_at', 'ends_at']);

        if (! $subscription) {
            return ['status' => 'none'];
        }

        $plan = SubscriptionPlan::query()->find($subscription->plan_id, ['name', 'duration_days']);

        return [
            'plan' => $plan?->name,
            'duration_days' => $plan?->duration_days,
            'status' => $subscription->status,
            'amount' => $subscription->amount,
            'paid_at' => $subscription->paid_at?->toIso8601String(),
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
        ];
    }

    private function familyProfile(Family $family): array
    {
        $members = FamilyMember::query()
            ->where('family_id', $family->id)
            ->whereNull('removed_at')
            ->with(['user:id,full_name'])
            ->orderBy('joined_at')
            ->get(['id', 'user_id', 'role', 'nickname', 'monthly_quota'])
            ->map(fn (FamilyMember $member) => [
                'name' => $member->nickname ?: $member->user?->full_name,
                'role' => $member->role,
                'monthly_quota' => $member->monthly_quota,
            ])->all();

        $answers = OnboardingAnswer::query()
            ->where('family_id', $family->id)
            ->where('skipped', false)
            ->get(['question_key', 'answer'])
            ->mapWithKeys(fn (OnboardingAnswer $answer) => [$answer->question_key => $answer->answer])
            ->all();

        return [
            'family_name' => $family->name,
            'currency' => $family->currency,
            'members' => $members,
            'onboarding_answers' => $answers,
        ];
    }
}
