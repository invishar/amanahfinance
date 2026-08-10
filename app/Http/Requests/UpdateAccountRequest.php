<?php

namespace App\Http\Requests;

use App\Support\CurrentFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('account'));
    }

    // opening_balance / current_balance are intentionally absent: the ledger
    // (transactions) is the only source of truth for balances (aturan #4).
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('accounts', 'name')
                    ->where('family_id', app(CurrentFamily::class)->id())
                    ->ignore($this->route('account')),
            ],
            'account_type' => ['sometimes', Rule::in(['bank', 'ewallet', 'cash', 'other'])],
            'institution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'masked_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner_member_id' => ['sometimes', 'nullable', 'uuid', 'exists:family_members,id'],
            'is_shared' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
