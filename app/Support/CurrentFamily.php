<?php

namespace App\Support;

use App\Models\Family;
use App\Models\FamilyMember;

/**
 * Request-scoped holder for the family resolved by ResolveFamily middleware.
 * Bound as a singleton so it is fresh for every request.
 */
class CurrentFamily
{
    private ?Family $family = null;

    private ?FamilyMember $member = null;

    public function set(Family $family, FamilyMember $member): void
    {
        $this->family = $family;
        $this->member = $member;
    }

    public function id(): ?string
    {
        return $this->family?->id;
    }

    public function family(): ?Family
    {
        return $this->family;
    }

    public function member(): ?FamilyMember
    {
        return $this->member;
    }

    public function memberId(): ?string
    {
        return $this->member?->id;
    }

    public function role(): ?string
    {
        return $this->member?->role;
    }
}
