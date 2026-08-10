<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Support\CurrentFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Account::class);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('accounts', 'name')
                    ->where('family_id', app(CurrentFamily::class)->id()),
            ],
            'account_type' => ['required', Rule::in(['bank', 'ewallet', 'cash', 'other'])],
            'institution' => ['nullable', 'string', 'max:255'],
            'masked_number' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['sometimes', 'integer', 'min:0'],
            'owner_member_id' => ['nullable', 'uuid', 'exists:family_members,id'],
            'is_shared' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
