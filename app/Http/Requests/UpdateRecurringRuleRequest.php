<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('recurring_rule'));
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::in(['income', 'expense', 'savings'])],
            'amount' => ['sometimes', 'required', 'integer', 'min:1'],
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
            'note' => ['sometimes', 'nullable', 'string'],
            'rrule' => ['sometimes', 'required', 'string', 'max:255'],
            'next_run_on' => ['sometimes', 'required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
