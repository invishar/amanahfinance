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
            // Tidak "sometimes" -- requiredIf harus tetap mengecek walau field
            // ini sama sekali tidak dikirim, supaya provider openai_compatible
            // (Groq, dst) tidak bisa disimpan tanpa base_url (OpenAiCompatible-
            // ConversationRunner butuh itu, beda dari Anthropic yang punya
            // default resmi).
            'base_url' => [
                Rule::requiredIf(fn () => $this->effectiveProvider() === 'openai_compatible'),
                'nullable', 'url', 'max:2048',
            ],
            // Wire protocol, bukan tebakan dari base_url/model -- lihat
            // llm_settings_provider_ck di migrasi.
            'provider' => ['sometimes', Rule::in(['anthropic', 'openai_compatible'])],
        ];
    }

    // Body ini boleh cuma sebagian field (mis. ganti model doang) -- provider
    // efektif yang dipakai untuk requiredIf harus lihat request dulu, baru
    // fallback ke baris DB yang sudah ada, baru ke .env.
    private function effectiveProvider(): string
    {
        return $this->input('provider')
            ?? LlmSetting::query()->value('provider')
            ?? config('services.llm.provider', 'anthropic');
    }
}
