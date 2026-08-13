<?php

namespace App\Actions\Families;

use App\Actions\Chat\OnboardingConversationActions;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FamilyActions
{
    public function __construct(private OnboardingConversationActions $onboarding) {}

    public function create(User $user, array $data): Family
    {
        return DB::transaction(function () use ($user, $data) {
            $family = Family::create([
                'name' => $data['name'],
                'currency' => $data['currency'] ?? 'IDR',
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
            ]);

            $member = FamilyMember::create([
                'family_id' => $family->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            // Naskah wawancara awal disimpan di server, bukan klien (CLAUDE.md
            // "Alur AI") -- sapaan + pertanyaan pertama Amina langsung muncul
            // begitu family (dan thread onboarding-nya) baru dibuat.
            $this->onboarding->start($family, $member);

            // fresh(): onboarding_done has a DB-level default that create()
            // doesn't reflect back onto the in-memory model.
            return $family->fresh();
        });
    }

    public function update(Family $family, array $data): Family
    {
        $family->update($data);

        return $family->fresh();
    }

    public function delete(Family $family): void
    {
        $family->delete();
    }
}
