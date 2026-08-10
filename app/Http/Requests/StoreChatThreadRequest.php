<?php

namespace App\Http\Requests;

use App\Models\ChatThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ChatThread::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::in(['general', 'onboarding'])],
        ];
    }
}
