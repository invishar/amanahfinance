<?php

namespace App\Http\Requests;

use App\Models\Family;
use Illuminate\Foundation\Http\FormRequest;

class StoreFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Family::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
