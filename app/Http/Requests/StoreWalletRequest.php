<?php

namespace App\Http\Requests;

use App\Models\Wallet;
use App\Support\CurrentFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Wallet::class);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('wallets', 'name')
                    ->where('family_id', app(CurrentFamily::class)->id()),
            ],
            'icon' => ['sometimes', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'monthly_budget' => ['sometimes', 'integer', 'min:0'],
            'rollover' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
