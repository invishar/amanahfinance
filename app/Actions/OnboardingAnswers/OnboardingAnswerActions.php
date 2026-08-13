<?php

namespace App\Actions\OnboardingAnswers;

use App\Actions\Chat\OnboardingConversationActions;
use App\Models\OnboardingAnswer;
use Illuminate\Support\Facades\DB;

class OnboardingAnswerActions
{
    public function __construct(private OnboardingConversationActions $onboarding) {}

    public function create(array $data): OnboardingAnswer
    {
        return DB::transaction(function () use ($data) {
            $answer = OnboardingAnswer::create([
                ...$data,
                'skipped' => $data['skipped'] ?? false,
                'answered_at' => now(),
            ]);

            // Menyisipkan pertanyaan berikutnya (atau menandai family selesai
            // onboarding) setiap kali satu jawaban masuk -- lihat
            // OnboardingConversationActions.
            $this->onboarding->advance();

            return $answer;
        });
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
