<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChatThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('chat_thread'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::in(['general', 'onboarding'])],
        ];
    }
}
