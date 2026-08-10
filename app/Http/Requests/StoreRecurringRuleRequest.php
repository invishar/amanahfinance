<?php

namespace App\Http\Requests;

use App\Models\RecurringRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RecurringRule::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['income', 'expense', 'savings'])],
            'amount' => ['required', 'integer', 'min:1'],
            'wallet_id' => [
                Rule::requiredIf(fn () => $this->input('type') === 'expense'),
                'nullable', 'uuid', 'exists:wallets,id',
            ],
            'source_id' => [
                Rule::requiredIf(fn () => $this->input('type') === 'income'),
                'nullable', 'uuid', 'exists:income_sources,id',
            ],
            'account_id' => [
                Rule::requiredIf(fn () => $this->input('type') === 'savings'),
                'nullable', 'uuid', 'exists:accounts,id',
            ],
            'note' => ['nullable', 'string'],
            'rrule' => ['required', 'string', 'max:255'],
            'next_run_on' => ['required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
