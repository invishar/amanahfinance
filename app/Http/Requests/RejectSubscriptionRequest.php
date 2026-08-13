<?php

namespace App\Http\Requests;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

class RejectSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reject', Subscription::class);
    }

    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'max:1000'],
        ];
    }
}
