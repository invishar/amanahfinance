<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptFamilyInviteRequest extends FormRequest
{
    // Any authenticated user may attempt to redeem a token -- the real
    // checks (validity, expiry, contact match, existing membership) live in
    // FamilyInviteActions::accept() and surface as 422s, not 403s, so a
    // wrong/stolen token doesn't leak whether it exists.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
        ];
    }
}
