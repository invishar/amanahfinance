<?php

namespace App\Actions\Subscriptions;

use App\Exceptions\ConflictException;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Support\CurrentFamily;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SubscriptionActions
{
    /**
     * @param  array{status?: string, per_page?: int}  $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = Subscription::query()->orderByDesc('created_at');

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * "Pilih paket" + "konfirmasi pembayaran" dalam satu langkah (lihat
     * StoreSubscriptionRequest): baris langsung berstatus pending_payment,
     * menunggu platform admin activate()/reject().
     */
    public function create(array $data): Subscription
    {
        $familyId = app(CurrentFamily::class)->id();

        $hasOpenSubscription = Subscription::query()
            ->whereIn('status', ['pending_payment', 'active'])
            ->exists();

        if ($hasOpenSubscription) {
            throw new ConflictException(
                'Family ini masih punya langganan yang aktif atau menunggu review admin.'
            );
        }

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);

        return Subscription::create([
            'family_id' => $familyId,
            'plan_id' => $plan->id,
            'status' => 'pending_payment',
            'amount' => $plan->price,
            'payment_note' => $data['payment_note'] ?? null,
            'payment_proof_url' => $data['payment_proof_url'] ?? null,
            'paid_at' => now(),
            'requested_by' => app(CurrentFamily::class)->memberId(),
        ])->fresh();
    }

    public function activate(Subscription $subscription): Subscription
    {
        $this->guardPendingPayment($subscription);

        $startsAt = now();

        $subscription->update([
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($subscription->plan->duration_days),
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $subscription->fresh();
    }

    public function reject(Subscription $subscription, string $reviewNote): Subscription
    {
        $this->guardPendingPayment($subscription);

        $subscription->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => $reviewNote,
        ]);

        return $subscription->fresh();
    }

    private function guardPendingPayment(Subscription $subscription): void
    {
        if ($subscription->status !== 'pending_payment') {
            throw ValidationException::withMessages([
                'status' => ["Langganan ini sudah berstatus \"{$subscription->status}\", tidak bisa diproses lagi."],
            ]);
        }
    }
}
