<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class FamilyMemberTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_admin_can_add_a_member(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        $newUser = User::factory()->create();

        $this->postJson('/api/v1/family-members', [
            'user_id' => $newUser->id,
            'role' => 'member',
            'nickname' => 'Kakak',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'member');

        $this->assertDatabaseHas('family_members', [
            'family_id' => $family->id,
            'user_id' => $newUser->id,
        ]);
    }

    public function test_member_cannot_add_a_member(): void
    {
        $this->actingAsFamilyMember('member');

        $this->postJson('/api/v1/family-members', [
            'user_id' => User::factory()->create()->id,
            'role' => 'viewer',
        ])->assertStatus(403);
    }

    public function test_destroy_soft_removes_instead_of_deleting_row(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        $target = FamilyMember::factory()->for($family)->create(['role' => 'member']);

        $this->deleteJson('/api/v1/family-members/'.$target->id)->assertNoContent();

        $this->assertDatabaseHas('family_members', ['id' => $target->id]);
        $this->assertNotNull($target->fresh()->removed_at);
    }

    public function test_tenant_leak_family_a_cannot_see_family_b_member(): void
    {
        $this->actingAsFamilyMember('admin');
        $otherFamily = Family::factory()->create();
        $otherMember = FamilyMember::factory()->for($otherFamily)->create();

        $this->getJson('/api/v1/family-members/'.$otherMember->id)->assertStatus(404);
    }
}
