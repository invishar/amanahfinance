<?php

namespace App\Http\Requests;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

class ActivateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('activate', Subscription::class);
    }

    public function rules(): array
    {
        return [];
    }
}
