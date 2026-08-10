<?php

namespace App\Http\Requests;

use App\Models\WalletBudget;
use Illuminate\Foundation\Http\FormRequest;

class StoreWalletBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', WalletBudget::class);
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:0'],
        ];
    }
}
