<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFamilyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('family_member'));
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'required', Rule::in(['admin', 'member', 'viewer'])],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'monthly_quota' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
