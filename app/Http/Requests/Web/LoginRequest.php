<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Login lewat sesi web (cookie). Aturannya sama persis dengan
 * App\Http\Requests\LoginRequest (API, Bearer token) — sengaja kelas
 * terpisah supaya perubahan pada salah satu alur tidak diam-diam mengubah
 * alur yang lain.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required_without:phone', 'nullable', 'email'],
            'phone' => ['required_without:email', 'nullable', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
