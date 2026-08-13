<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Otorisasi tetap lewat $this->authorize() di controller (pola index() lain
// di app ini) -- FormRequest ini murni validasi query param.
class IndexChatThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::in(['general', 'onboarding'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
