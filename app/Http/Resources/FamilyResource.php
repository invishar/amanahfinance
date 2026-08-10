<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Family */
class FamilyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'onboarding_done' => $this->onboarding_done,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
