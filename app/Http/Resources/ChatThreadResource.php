<?php

namespace App\Http\Resources;

use App\Models\ChatThread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatThread */
class ChatThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'member_id' => $this->member_id,
            'title' => $this->title,
            'kind' => $this->kind,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,
            'onboarding' => $this->kind === 'onboarding' ? $this->onboardingProgress() : null,
        ];
    }

    /**
     * Sejak wawancara awal dijalankan Amina sendiri (bukan wizard berlangkah
     * tetap), tidak ada lagi `step`/`total`/`question_key` yang bermakna --
     * jumlah giliran tanya-jawab ditentukan percakapan, bukan naskah. Klien
     * cukup tahu apakah mode wawancara masih berjalan; penandanya
     * families.onboarding_done, yang dinyalakan tool finish_onboarding.
     *
     * @return array{done: bool}
     */
    private function onboardingProgress(): array
    {
        return [
            'done' => (bool) $this->family->onboarding_done,
        ];
    }
}
