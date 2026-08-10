<?php

namespace App\Actions\Families;

use App\Models\FamilyInvite;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyInviteActions
{
    public function create(User $inviter, array $data): FamilyInvite
    {
        $invite = FamilyInvite::create([
            ...$data,
            'invited_by' => $inviter->id,
            'role' => $data['role'] ?? 'member',
            'token' => 'AMANA-'.Str::upper(Str::random(6)),
            'expires_at' => now()->addDays(7),
        ]);

        // created_at is DB useCurrent(), not set by create() itself.
        return $invite->fresh();
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

    /**
     * Redeem an invite token into a family_members row for $user. The user
     * accepting has no membership in the target family yet, so this runs
     * outside resolve.family -- every lookup here is deliberately unscoped.
     */
    public function accept(User $user, string $token): FamilyMember
    {
        $invite = FamilyInvite::query()
            ->withoutGlobalScope('family')
            ->where('token', $token)
            ->first();

        if (! $invite) {
            throw ValidationException::withMessages([
                'token' => ['Kode undangan tidak valid.'],
            ]);
        }

        if ($invite->accepted_at !== null) {
            throw ValidationException::withMessages([
                'token' => ['Kode undangan sudah dipakai.'],
            ]);
        }

        if ($invite->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['Kode undangan sudah kedaluwarsa.'],
            ]);
        }

        $contactMatches = ($invite->email && $user->email && strcasecmp($invite->email, $user->email) === 0)
            || ($invite->phone && $user->phone && $invite->phone === $user->phone);

        if (! $contactMatches) {
            throw ValidationException::withMessages([
                'token' => ['Undangan ini bukan untuk akun Anda.'],
            ]);
        }

        $alreadyMember = FamilyMember::query()
            ->withoutGlobalScope('family')
            ->where('family_id', $invite->family_id)
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'token' => ['Anda sudah menjadi anggota family ini.'],
            ]);
        }

        return DB::transaction(function () use ($invite, $user) {
            $member = FamilyMember::create([
                'family_id' => $invite->family_id,
                'user_id' => $user->id,
                'role' => $invite->role,
                'joined_at' => now(),
            ]);

            $invite->update(['accepted_at' => now()]);

            return $member->load('user');
        });
    }
}
