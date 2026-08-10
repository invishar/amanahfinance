<?php

use App\Models\Family;
use App\Models\FamilyInvite;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('admin can create invite with generated token', function () {
    [, $family] = $this->actingAsFamilyMember('admin');

    $response = $this->postJson('/api/v1/family-invites', ['email' => 'calon@keluarga.test'])
        ->assertCreated();

    $this->assertStringStartsWith('AMANA-', $response->json('data.token'));
    $this->assertDatabaseHas('family_invites', [
        'family_id' => $family->id,
        'email' => 'calon@keluarga.test',
        'role' => 'member',
    ]);
});

test('member cannot create invite', function () {
    $this->actingAsFamilyMember('member');

    $this->postJson('/api/v1/family-invites', ['email' => 'calon@keluarga.test'])->assertStatus(403);
});

test('email or phone is required', function () {
    $this->actingAsFamilyMember('admin');

    $this->postJson('/api/v1/family-invites', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('tenant leak cannot view other familys invite', function () {
    $this->actingAsFamilyMember('admin');
    $otherFamily = Family::factory()->create();
    $otherInvite = FamilyInvite::factory()->for($otherFamily)->create();

    $this->getJson('/api/v1/family-invites/'.$otherInvite->id)->assertStatus(404);
});

test('accept creates membership and marks invite accepted', function () {
    $family = Family::factory()->create();
    $invite = FamilyInvite::factory()->for($family)->create([
        'email' => 'calon@keluarga.test',
        'role' => 'member',
    ]);
    $newUser = User::factory()->create(['email' => 'calon@keluarga.test']);
    Sanctum::actingAs($newUser);

    $this->postJson('/api/v1/family-invites/accept', ['token' => $invite->token])
        ->assertCreated()
        ->assertJsonPath('data.family_id', $family->id)
        ->assertJsonPath('data.role', 'member');

    $this->assertDatabaseHas('family_members', [
        'family_id' => $family->id,
        'user_id' => $newUser->id,
        'role' => 'member',
    ]);
    $this->assertNotNull($invite->fresh()->accepted_at);
});

test('accept rejects an unknown token', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/family-invites/accept', ['token' => 'AMANA-NOPE00'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('accept rejects a token whose contact does not match the accepting user', function () {
    $invite = FamilyInvite::factory()->for(Family::factory())->create(['email' => 'calon@keluarga.test']);
    Sanctum::actingAs(User::factory()->create(['email' => 'orang-lain@example.test']));

    $this->postJson('/api/v1/family-invites/accept', ['token' => $invite->token])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('accept rejects an already accepted invite', function () {
    $invite = FamilyInvite::factory()->for(Family::factory())->accepted()->create(['email' => 'calon@keluarga.test']);
    Sanctum::actingAs(User::factory()->create(['email' => 'calon@keluarga.test']));

    $this->postJson('/api/v1/family-invites/accept', ['token' => $invite->token])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('accept rejects an expired invite', function () {
    $invite = FamilyInvite::factory()->for(Family::factory())->create([
        'email' => 'calon@keluarga.test',
        'expires_at' => now()->subDay(),
    ]);
    Sanctum::actingAs(User::factory()->create(['email' => 'calon@keluarga.test']));

    $this->postJson('/api/v1/family-invites/accept', ['token' => $invite->token])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('accept rejects when user is already a member of that family', function () {
    [$user, $family] = $this->actingAsFamilyMember('member');
    $invite = FamilyInvite::factory()->for($family)->create(['email' => $user->email]);

    $this->postJson('/api/v1/family-invites/accept', ['token' => $invite->token])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});
