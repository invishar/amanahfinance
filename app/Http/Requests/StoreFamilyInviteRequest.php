<?php

namespace App\Http\Requests;

use App\Models\FamilyInvite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFamilyInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FamilyInvite::class);
    }

    public function rules(): array
    {
        return [
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', Rule::in(['admin', 'member', 'viewer'])],
        ];
    }
}
