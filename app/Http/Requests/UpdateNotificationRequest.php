<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('notification'));
    }

    // In practice clients only use this to toggle read_at (mark read/unread).
    public function rules(): array
    {
        return [
            'read_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
