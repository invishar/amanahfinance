<?php

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('store creates family and makes creator admin', function () {
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
});

test('index only lists families the user belongs to', function () {
    $user = User::factory()->create();
    $ownFamily = Family::factory()->create();
    FamilyMember::factory()->for($ownFamily)->for($user)->create(['role' => 'member']);
    Family::factory()->create(); // someone else's family

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/families')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownFamily->id);
});

test('non member cannot view family', function () {
    $family = Family::factory()->create();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/families/'.$family->id)->assertStatus(403);
});

test('only admin can update family', function () {
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
});

test('only admin can delete family', function () {
    $family = Family::factory()->create();
    $member = User::factory()->create();
    FamilyMember::factory()->for($family)->for($member)->create(['role' => 'member']);
    Sanctum::actingAs($member);

    $this->deleteJson('/api/v1/families/'.$family->id)->assertStatus(403);
    $this->assertDatabaseHas('families', ['id' => $family->id]);
});
