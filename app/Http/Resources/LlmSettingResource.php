<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// The raw key is NEVER included here (aturan #7) -- only whether one is set
// and a non-sensitive last-4-chars preview for the admin to recognize it.
/** @mixin \App\Models\LlmSetting */
class LlmSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'model' => $this->model,
            'base_url' => $this->base_url,
            'has_key' => filled($this->key),
            'key_preview' => filled($this->key) ? '...'.substr($this->key, -4) : null,
            'updated_at' => $this->updated_at,
            'updated_by' => $this->updated_by,
        ];
    }
}
