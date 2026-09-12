<?php

namespace App\Http\Requests;

use App\Models\LlmSetting;
use Illuminate\Foundation\Http\FormRequest;

class FetchNineRouterModelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', LlmSetting::class);
    }

    public function rules(): array
    {
        return [
            'base_url' => ['required', 'url:http,https', 'max:2048'],
            'key' => ['sometimes', 'nullable', 'string', 'min:8', 'max:8192'],
        ];
    }
}
