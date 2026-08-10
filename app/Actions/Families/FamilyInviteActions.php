<?php

namespace App\Actions\Families;

use App\Models\FamilyInvite;
use App\Models\User;
use Illuminate\Support\Str;

class FamilyInviteActions
{
    public function create(User $inviter, array $data): FamilyInvite
    {
        return FamilyInvite::create([
            ...$data,
            'invited_by' => $inviter->id,
            'role' => $data['role'] ?? 'member',
            'token' => 'AMANA-'.Str::upper(Str::random(6)),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function update(FamilyInvite $familyInvite, array $data): FamilyInvite
    {
        $familyInvite->update($data);

        return $familyInvite->fresh();
    }

    public function delete(FamilyInvite $familyInvite): void
    {
        $familyInvite->delete();
    }
}
