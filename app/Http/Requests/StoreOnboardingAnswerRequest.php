<?php

namespace App\Http\Requests;

use App\Models\OnboardingAnswer;
use App\Support\CurrentFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', OnboardingAnswer::class);
    }

    public function rules(): array
    {
        return [
            'question_key' => [
                'required', 'string', 'max:255',
                Rule::unique('onboarding_answers', 'question_key')
                    ->where('family_id', app(CurrentFamily::class)->id()),
            ],
            'answer' => ['nullable', 'array'],
            'skipped' => ['sometimes', 'boolean'],
        ];
    }
}
