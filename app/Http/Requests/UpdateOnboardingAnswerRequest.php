<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOnboardingAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('onboarding_answer'));
    }

    // question_key is immutable after creation -- it's the identity of the answer.
    public function rules(): array
    {
        return [
            'answer' => ['sometimes', 'nullable', 'array'],
            'skipped' => ['sometimes', 'boolean'],
        ];
    }
}
