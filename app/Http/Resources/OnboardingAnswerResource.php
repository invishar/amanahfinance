<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OnboardingAnswer */
class OnboardingAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'question_key' => $this->question_key,
            'answer' => $this->answer,
            'skipped' => $this->skipped,
            'answered_at' => $this->answered_at,
        ];
    }
}
