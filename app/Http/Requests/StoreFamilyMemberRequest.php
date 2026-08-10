<?php

namespace App\Http\Requests;

use App\Models\FamilyMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFamilyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FamilyMember::class);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', Rule::in(['admin', 'member', 'viewer'])],
            'nickname' => ['nullable', 'string', 'max:255'],
            'monthly_quota' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
