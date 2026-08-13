<?php

namespace App\Http\Requests;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// One request covers both "pilih paket" and "konfirmasi pembayaran" (CLAUDE.md
// ask keeps the flow simple): submitting this already marks the request as
// paid (paid_at = now), leaving it in status=pending_payment awaiting admin
// review. plan.is_active / duplicate-request checks live in SubscriptionActions.
class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Subscription::class);
    }

    public function rules(): array
    {
        return [
            'plan_id' => [
                'required', 'uuid',
                Rule::exists('subscription_plans', 'id')->where('is_active', true),
            ],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            // URL dari POST /uploads (foto bukti transfer) -- string biasa,
            // sama seperti transactions.receipt_url (lihat TransactionRules).
            'payment_proof_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
