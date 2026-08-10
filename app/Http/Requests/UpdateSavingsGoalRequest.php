<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('savings_goal'));
    }

    public function rules(): array
    {
        return [
            'target_name' => ['sometimes', 'required', 'string', 'max:255'],
            'target_amount' => ['sometimes', 'required', 'integer', 'min:1'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_id' => ['sometimes', 'nullable', 'uuid', 'exists:accounts,id'],
            'status' => ['sometimes', Rule::in(['active', 'achieved', 'paused', 'cancelled'])],
        ];
    }
}
