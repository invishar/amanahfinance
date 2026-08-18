<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            // 'none' = family pertama user belum pernah mengajukan langganan
            // (atau user belum punya family sama sekali).
            'subscription_status' => ['nullable', Rule::in(['pending_payment', 'active', 'rejected', 'expired', 'none'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
