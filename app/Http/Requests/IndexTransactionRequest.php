<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Otorisasi tetap lewat $this->authorize() di controller (pola index() lain
// di app ini) -- FormRequest ini murni validasi query param.
class IndexTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'wallet_id' => ['nullable', 'uuid'],
            'account_id' => ['nullable', 'uuid'],
            'type' => ['nullable', Rule::in(['income', 'expense', 'transfer', 'savings'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
