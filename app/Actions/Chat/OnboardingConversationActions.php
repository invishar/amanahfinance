<?php

namespace App\Actions\Chat;

use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\OnboardingAnswer;
use App\Support\CurrentFamily;

// Naskah wawancara awal hidup di config('amina.onboarding_questions'), bukan
// di klien (CLAUDE.md, "Alur AI"). Kelas ini satu-satunya tempat yang menulis
// pesan Amina ke thread kind=onboarding: start() dipanggil dari
// FamilyActions saat family baru dibuat, advance() dipanggil dari
// OnboardingAnswerActions setiap kali jawaban baru masuk.
class OnboardingConversationActions
{
    public function start(Family $family, FamilyMember $member): void
    {
        $thread = $family->chatThreads()->create([
            'member_id' => $member->id,
            'kind' => 'onboarding',
            'title' => 'Kenalan sama Amina',
        ]);

        // Cuma pembuka. Pertanyaan lanjutannya TIDAK lagi diskrip di sini --
        // begitu user membalas, ProcessAssistantMessage jalan seperti chat
        // biasa dan Amina mewawancarai sendiri di bawah `onboarding_briefing`,
        // sambil menyiapkan draft lewat tool create_*. Lihat
        // AssistantService::respond() ($isOnboarding).
        $lastMessage = $thread->messages()->create([
            'role' => 'assistant',
            'content' => config('amina.onboarding_greeting'),
        ]);

        $thread->update(['last_message_at' => $lastMessage->created_at]);
    }

    // Tanpa parameter, seperti Action lain yang membaca family dari request
    // yang sedang berjalan (mis. TransactionActions::create) -- dipanggil
    // dari dalam grup route resolve.family, jadi CurrentFamily selalu terisi.
    public function advance(): void
    {
        $family = app(CurrentFamily::class)->family();
        $questions = config('amina.onboarding_questions');

        $answeredKeys = OnboardingAnswer::query()->where('family_id', $family->id)->pluck('question_key')->all();
        $nextKey = collect($questions)->keys()->first(fn ($key) => ! in_array($key, $answeredKeys, true));

        if ($nextKey === null) {
            $family->update(['onboarding_done' => true]);

            return;
        }

        $thread = ChatThread::query()->where('kind', 'onboarding')->first();

        if (! $thread) {
            return;
        }

        $message = $thread->messages()->create(['role' => 'assistant', 'content' => $questions[$nextKey]]);
        $thread->update(['last_message_at' => $message->created_at]);
    }
}
