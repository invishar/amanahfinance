<?php

namespace App\Http\Requests;

use App\Models\ChatMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ChatMessage::class);
    }

    // role is never accepted here: client-created messages are always
    // role=user. Assistant/system replies are written by AssistantService.
    public function rules(): array
    {
        return [
            'content' => ['required_without:attachment_url', 'nullable', 'string'],
            'input_mode' => ['nullable', Rule::in(['text', 'voice', 'image'])],
            'attachment_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
