<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FamilyTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_family_and_makes_creator_admin(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/families', ['name' => 'Keluarga Baru'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Keluarga Baru')
            ->assertJsonPath('data.currency', 'IDR');

        $familyId = $response->json('data.id');

        $this->assertDatabaseHas('family_members', [
            'family_id' => $familyId,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_index_only_lists_families_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        $ownFamily = Family::factory()->create();
        FamilyMember::factory()->for($ownFamily)->for($user)->create(['role' => 'member']);
        Family::factory()->create(); // someone else's family

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/families')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownFamily->id);
    }

    public function test_non_member_cannot_view_family(): void
    {
        $family = Family::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/families/'.$family->id)->assertStatus(403);
    }

    public function test_only_admin_can_update_family(): void
    {
        $family = Family::factory()->create();
        $member = User::factory()->create();
        FamilyMember::factory()->for($family)->for($member)->create(['role' => 'member']);
        Sanctum::actingAs($member);

        $this->putJson('/api/v1/families/'.$family->id, ['name' => 'Ganti Nama'])->assertStatus(403);

        $admin = User::factory()->create();
        FamilyMember::factory()->for($family)->for($admin)->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/families/'.$family->id, ['name' => 'Ganti Nama'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ganti Nama');
    }

    public function test_only_admin_can_delete_family(): void
    {
        $family = Family::factory()->create();
        $member = User::factory()->create();
        FamilyMember::factory()->for($family)->for($member)->create(['role' => 'member']);
        Sanctum::actingAs($member);

        $this->deleteJson('/api/v1/families/'.$family->id)->assertStatus(403);
        $this->assertDatabaseHas('families', ['id' => $family->id]);
    }
}
