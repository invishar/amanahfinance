<?php

namespace App\Actions\LlmSettings;

use App\Models\LlmSetting;
use App\Models\User;

class LlmSettingActions
{
    /**
     * The single active settings row, or an in-memory (unsaved) fallback
     * built from .env so the admin sees what's effectively in use before
     * ever configuring the DB-backed override.
     */
    public function current(): LlmSetting
    {
        return LlmSetting::query()->first() ?? new LlmSetting([
            'key' => config('services.llm.key') ?: null,
            'model' => config('services.llm.model'),
            'base_url' => config('services.llm.base_url') ?: null,
            'provider' => config('services.llm.provider', 'anthropic'),
        ]);
    }

    public function update(User $user, array $data): LlmSetting
    {
        $setting = LlmSetting::query()->first() ?? new LlmSetting;

        $setting->model = $data['model'];
        $setting->base_url = $data['base_url'] ?? null;
        $setting->provider = $data['provider'] ?? $setting->provider ?? 'anthropic';

        if (array_key_exists('key', $data) && filled($data['key'])) {
            $setting->key = $data['key'];
        } elseif (! $setting->exists) {
            // First-ever save with no key supplied: seed from .env instead
            // of persisting an empty key.
            $setting->key = config('services.llm.key') ?: null;
        }

        $setting->updated_by = $user->id;
        $setting->save();

        return $setting->fresh();
    }
}
