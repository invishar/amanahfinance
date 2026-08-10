<?php

namespace App\Http\Requests;

use App\Models\SavingsGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SavingsGoal::class);
    }

    // current_amount is intentionally absent: it is a cache derived from
    // 'savings' transactions (aturan #4), never set directly.
    public function rules(): array
    {
        return [
            'target_name' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'integer', 'min:1'],
            'deadline' => ['nullable', 'date'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'account_id' => ['nullable', 'uuid', 'exists:accounts,id'],
            'status' => ['sometimes', Rule::in(['active', 'achieved', 'paused', 'cancelled'])],
        ];
    }
}
