<?php

namespace App\Http\Requests;

use App\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Notification::class);
    }

    public function rules(): array
    {
        return [
            'member_id' => ['nullable', 'uuid', 'exists:family_members,id'],
            'kind' => ['required', Rule::in(['budget_warning', 'goal_progress', 'bill_due', 'weekly_digest'])],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'deeplink' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
