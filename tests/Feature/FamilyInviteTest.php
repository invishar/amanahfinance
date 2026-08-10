<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class FamilyInviteTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_admin_can_create_invite_with_generated_token(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');

        $response = $this->postJson('/api/v1/family-invites', ['email' => 'calon@keluarga.test'])
            ->assertCreated();

        $this->assertStringStartsWith('AMANA-', $response->json('data.token'));
        $this->assertDatabaseHas('family_invites', [
            'family_id' => $family->id,
            'email' => 'calon@keluarga.test',
            'role' => 'member',
        ]);
    }

    public function test_member_cannot_create_invite(): void
    {
        $this->actingAsFamilyMember('member');

        $this->postJson('/api/v1/family-invites', ['email' => 'calon@keluarga.test'])->assertStatus(403);
    }

    public function test_email_or_phone_is_required(): void
    {
        $this->actingAsFamilyMember('admin');

        $this->postJson('/api/v1/family-invites', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_tenant_leak_cannot_view_other_familys_invite(): void
    {
        $this->actingAsFamilyMember('admin');
        $otherFamily = Family::factory()->create();
        $otherInvite = FamilyInvite::factory()->for($otherFamily)->create();

        $this->getJson('/api/v1/family-invites/'.$otherInvite->id)->assertStatus(404);
    }
}
