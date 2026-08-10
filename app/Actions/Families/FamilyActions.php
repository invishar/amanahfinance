<?php

namespace App\Actions\Families;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FamilyActions
{
    public function create(User $user, array $data): Family
    {
        return DB::transaction(function () use ($user, $data) {
            $family = Family::create([
                'name' => $data['name'],
                'currency' => $data['currency'] ?? 'IDR',
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
            ]);

            FamilyMember::create([
                'family_id' => $family->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'joined_at' => now(),
            ]);

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
