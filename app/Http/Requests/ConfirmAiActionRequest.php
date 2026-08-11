<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// The request body itself is the edit set (a subset of the draft's payload
// keys) -- field-level rules depend on the ai_action's own `action` type, so
// they're validated inside ConfirmAiAction against the merged payload rather
// than here. This just gates authorization.
class ConfirmAiActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('confirm', $this->route('ai_action'));
    }

    public function rules(): array
    {
        return [];
    }
}
