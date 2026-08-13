<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('subscription_plan'));
    }

    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('subscription_plans', 'code')->ignore($this->route('subscription_plan')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'integer', 'min:1'],
            'duration_days' => ['sometimes', 'required', 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
