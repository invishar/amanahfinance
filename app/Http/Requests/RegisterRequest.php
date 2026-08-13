<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // email/phone: at least one required, matching the users_contact_ck
    // DB constraint (see 2026_01_01_000200_create_users_table.php).
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:255', 'unique:users,phone'],
            // password_confirmation opsional: desain FE cuma punya satu field
            // sandi (PLAN-INTEGRASI-FRONTEND.md §3.6). Kalau dikirim, tetap
            // harus cocok -- kalau tidak dikirim, tidak diwajibkan sama sekali.
            'password' => [
                'required', 'string', 'min:8',
                Rule::when($this->filled('password_confirmation'), ['confirmed']),
            ],
        ];
    }
}
