<?php

namespace App\Actions\OnboardingAnswers;

use App\Models\OnboardingAnswer;

class OnboardingAnswerActions
{
    public function create(array $data): OnboardingAnswer
    {
        return OnboardingAnswer::create([
            ...$data,
            'skipped' => $data['skipped'] ?? false,
            'answered_at' => now(),
        ]);
    }

    public function update(OnboardingAnswer $onboardingAnswer, array $data): OnboardingAnswer
    {
        $onboardingAnswer->update([...$data, 'answered_at' => now()]);

        return $onboardingAnswer->fresh();
    }

    public function delete(OnboardingAnswer $onboardingAnswer): void
    {
        $onboardingAnswer->delete();
    }
}
