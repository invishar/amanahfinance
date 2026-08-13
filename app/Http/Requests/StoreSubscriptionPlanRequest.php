<?php

namespace App\Http\Requests;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SubscriptionPlan::class);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'unique:subscription_plans,code'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:1'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
