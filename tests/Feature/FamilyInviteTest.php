<?php

use App\Models\Family;
use App\Models\FamilyInvite;

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
