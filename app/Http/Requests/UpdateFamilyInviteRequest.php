<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFamilyInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('family_invite'));
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::in(['admin', 'member', 'viewer'])],
            'expires_at' => ['sometimes', 'date'],
        ];
    }
}
