<?php

namespace App\Http\Requests;

use App\Support\CurrentFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('wallet'));
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('wallets', 'name')
                    ->where('family_id', app(CurrentFamily::class)->id())
                    ->ignore($this->route('wallet')),
            ],
            'icon' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:255'],
            'monthly_budget' => ['sometimes', 'integer', 'min:0'],
            'rollover' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
