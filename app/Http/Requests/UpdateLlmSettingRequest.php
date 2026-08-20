<?php

namespace App\Http\Requests;

use App\Models\LlmSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLlmSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', LlmSetting::class);
    }

    // key opsional: kosongkan/hilangkan field ini di body untuk mempertahankan
    // key yang sudah tersimpan -- klien tidak pernah bisa membaca key balik
    // lewat GET, jadi tidak ada cara mereka tahu key saat ini untuk resend.
    public function rules(): array
    {
        return [
            'key' => ['sometimes', 'nullable', 'string', 'min:8'],
            'model' => ['required', 'string', 'max:255'],
            'base_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            // Wire protocol, bukan tebakan dari base_url/model -- lihat
            // llm_settings_provider_ck di migrasi.
            'provider' => ['sometimes', Rule::in(['anthropic', 'openai_compatible'])],
        ];
    }
}
