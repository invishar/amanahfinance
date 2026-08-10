<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResolveFamilyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_any_family_membership_gets_403(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/wallets')->assertStatus(403);
    }

    public function test_defaults_to_first_membership_without_header(): void
    {
        $family = Family::factory()->create();
        $user = User::factory()->create();
        FamilyMember::factory()->for($family)->for($user)->create(['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/wallets')->assertOk();
    }

    public function test_x_family_id_header_switches_active_family(): void
    {
        $familyA = Family::factory()->create();
        $familyB = Family::factory()->create();
        $user = User::factory()->create();
        FamilyMember::factory()->for($familyA)->for($user)->create(['role' => 'admin']);
        FamilyMember::factory()->for($familyB)->for($user)->create(['role' => 'admin']);

        Wallet::factory()->for($familyA)->create(['name' => 'Wallet A']);
        Wallet::factory()->for($familyB)->create(['name' => 'Wallet B']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/wallets')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Wallet A'])
            ->assertJsonMissing(['name' => 'Wallet B']);

        $this->getJson('/api/v1/wallets', ['X-Family-Id' => $familyB->id])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Wallet B'])
            ->assertJsonMissing(['name' => 'Wallet A']);
    }

    public function test_x_family_id_header_rejected_when_not_a_member(): void
    {
        $family = Family::factory()->create();
        $otherFamily = Family::factory()->create();
        $user = User::factory()->create();
        FamilyMember::factory()->for($family)->for($user)->create(['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/wallets', ['X-Family-Id' => $otherFamily->id])->assertStatus(403);
    }

    public function test_removed_membership_is_not_selectable(): void
    {
        $family = Family::factory()->create();
        $user = User::factory()->create();
        FamilyMember::factory()->for($family)->for($user)->removed()->create(['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/wallets')->assertStatus(403);
    }
}
