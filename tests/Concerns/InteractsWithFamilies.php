<?php

namespace Tests\Concerns;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait InteractsWithFamilies
{
    /**
     * Create a family with one member of the given role and authenticate as
     * that member's user. Returns [$user, $family, $member].
     */
    protected function actingAsFamilyMember(string $role = 'admin', ?Family $family = null): array
    {
        $family ??= Family::factory()->create();
        $user = User::factory()->create();
        $member = FamilyMember::factory()->for($family)->for($user)->create(['role' => $role]);

        Sanctum::actingAs($user);

        return [$user, $family, $member];
    }
}
