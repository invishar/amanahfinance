<?php

namespace App\Http\Requests;

use App\Models\IncomeSource;
use App\Support\CurrentFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncomeSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', IncomeSource::class);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('income_sources', 'name')
                    ->where('family_id', app(CurrentFamily::class)->id()),
            ],
            'owner_member_id' => ['nullable', 'uuid', 'exists:family_members,id'],
            'expected_amount' => ['nullable', 'integer', 'min:0'],
            'cadence' => ['nullable', Rule::in(['monthly', 'biweekly', 'weekly', 'irregular'])],
            'is_archived' => ['sometimes', 'boolean'],
        ];
    }
}
