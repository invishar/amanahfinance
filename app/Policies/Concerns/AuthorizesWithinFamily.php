<?php

namespace App\Policies\Concerns;

use App\Support\CurrentFamily;

trait AuthorizesWithinFamily
{
    protected function currentFamilyId(): ?string
    {
        return app(CurrentFamily::class)->id();
    }

    protected function currentRole(): ?string
    {
        return app(CurrentFamily::class)->role();
    }

    protected function roleIn(array $roles): bool
    {
        return in_array($this->currentRole(), $roles, true);
    }

    protected function belongsToCurrentFamily(?string $familyId): bool
    {
        return $familyId !== null && $familyId === $this->currentFamilyId();
    }
}
