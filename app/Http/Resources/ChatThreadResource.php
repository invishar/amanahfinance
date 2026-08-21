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
     * @return array{step: int, total: int, done: bool, question_key: ?string}
     */
    private function onboardingProgress(): array
    {
        $questions = config('amina.onboarding_questions');
        $total = count($questions);
        $answeredKeys = OnboardingAnswer::query()->where('family_id', $this->family_id)->pluck('question_key')->all();
        $answered = count($answeredKeys);

        // Klien butuh key ini untuk mengisi `question_key` saat POST
        // /onboarding-answers (mis. tombol "Lewati") -- naskah pertanyaan
        // sendiri tetap tidak pernah dikirim dari sini, cuma identitasnya.
        $nextKey = collect($questions)->keys()->first(fn ($key) => ! in_array($key, $answeredKeys, true));

        return [
            'step' => min($answered + 1, $total),
            'total' => $total,
            'done' => $answered >= $total,
            'question_key' => $nextKey,
        ];
    }
}
