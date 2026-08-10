<?php

namespace App\Http\Requests;

use App\Support\CurrentFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncomeSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('income_source'));
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('income_sources', 'name')
                    ->where('family_id', app(CurrentFamily::class)->id())
                    ->ignore($this->route('income_source')),
            ],
            'owner_member_id' => ['sometimes', 'nullable', 'uuid', 'exists:family_members,id'],
            'expected_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cadence' => ['sometimes', 'nullable', Rule::in(['monthly', 'biweekly', 'weekly', 'irregular'])],
            'is_archived' => ['sometimes', 'boolean'],
        ];
    }
}
