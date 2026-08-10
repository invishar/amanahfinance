<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWalletBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('budget'));
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'required', 'date'],
            'amount' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
