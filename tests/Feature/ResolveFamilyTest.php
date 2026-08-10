<?php

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Models\Wallet;
use Laravel\Sanctum\Sanctum;

test('user without any family membership gets 403', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/wallets')->assertStatus(403);
});

test('defaults to first membership without header', function () {
    $family = Family::factory()->create();
    $user = User::factory()->create();
    FamilyMember::factory()->for($family)->for($user)->create(['role' => 'admin']);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/wallets')->assertOk();
});

test('x family id header switches active family', function () {
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
});

test('x family id header rejected when not a member', function () {
    $family = Family::factory()->create();
    $otherFamily = Family::factory()->create();
    $user = User::factory()->create();
    FamilyMember::factory()->for($family)->for($user)->create(['role' => 'admin']);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/wallets', ['X-Family-Id' => $otherFamily->id])->assertStatus(403);
});

test('removed membership is not selectable', function () {
    $family = Family::factory()->create();
    $user = User::factory()->create();
    FamilyMember::factory()->for($family)->for($user)->removed()->create(['role' => 'admin']);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/wallets')->assertStatus(403);
});
