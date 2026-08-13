<?php

namespace App\Http\Resources;

use App\Models\ChatThread;
use App\Models\OnboardingAnswer;
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
     * @return array{step: int, total: int, done: bool}
     */
    private function onboardingProgress(): array
    {
        $total = count(config('amina.onboarding_questions'));
        $answered = OnboardingAnswer::query()->where('family_id', $this->family_id)->count();

        return [
            'step' => min($answered + 1, $total),
            'total' => $total,
            'done' => $answered >= $total,
        ];
    }
}
