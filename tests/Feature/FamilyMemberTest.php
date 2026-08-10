<?php

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;

test('admin can add a member', function () {
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
});

test('member cannot add a member', function () {
    $this->actingAsFamilyMember('member');

    $this->postJson('/api/v1/family-members', [
        'user_id' => User::factory()->create()->id,
        'role' => 'viewer',
    ])->assertStatus(403);
});

test('destroy soft removes instead of deleting row', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    $target = FamilyMember::factory()->for($family)->create(['role' => 'member']);

    $this->deleteJson('/api/v1/family-members/'.$target->id)->assertNoContent();

    $this->assertDatabaseHas('family_members', ['id' => $target->id]);
    $this->assertNotNull($target->fresh()->removed_at);
});

test('tenant leak family a cannot see family b member', function () {
    $this->actingAsFamilyMember('admin');
    $otherFamily = Family::factory()->create();
    $otherMember = FamilyMember::factory()->for($otherFamily)->create();

    $this->getJson('/api/v1/family-members/'.$otherMember->id)->assertStatus(404);
});
