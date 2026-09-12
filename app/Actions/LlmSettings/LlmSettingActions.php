<?php

namespace App\Actions\LlmSettings;

use App\Models\LlmSetting;
use App\Models\User;
use App\Services\Ai\NineRouterCatalog;

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
            'gateway' => 'direct',
            'selection_mode' => 'model',
        ]);
    }

    public function update(User $user, array $data): LlmSetting
    {
        $setting = LlmSetting::query()->first() ?? new LlmSetting;

        $gateway = $data['gateway'] ?? $setting->gateway ?? 'direct';
        $mode = $gateway === '9router' ? ($data['selection_mode'] ?? $setting->selection_mode ?? 'model') : 'model';
        if ($gateway === '9router') {
            $data = app(NineRouterCatalog::class)->validateSelection([...$data, 'selection_mode' => $mode]);
        }

        $setting->model = $data['model'];
        $setting->base_url = $data['base_url'] ?? null;
        $setting->provider = $data['provider'] ?? $setting->provider ?? 'anthropic';
        $setting->gateway = $gateway;
        $setting->selection_mode = $mode;

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
