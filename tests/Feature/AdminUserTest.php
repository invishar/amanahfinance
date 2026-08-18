<?php

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('unauthenticated request is rejected', function () {
    $this->getJson('/api/v1/admin/users')->assertStatus(401);
});

test('non admin cannot list users', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

    $this->getJson('/api/v1/admin/users')->assertStatus(403);
});

test('non admin cannot view a user', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false]));
    $target = User::factory()->create();

    $this->getJson("/api/v1/admin/users/{$target->id}")->assertStatus(403);
});

test('admin can list users across every family', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    User::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/admin/users')->assertOk();

    // 1 admin + 3 others.
    expect($response->json('meta.total'))->toBe(4);
});

test('admin can search users by name, email or phone', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    User::factory()->create(['full_name' => 'Budi Santoso', 'email' => 'budi@example.test']);
    User::factory()->create(['full_name' => 'Siti Aminah', 'email' => 'siti@example.test']);

    $response = $this->getJson('/api/v1/admin/users?search=budi')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.full_name'))->toBe('Budi Santoso');
});

test('admin can view a user detail including family memberships', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $target = User::factory()->create();
    $family = Family::factory()->create(['name' => 'Keluarga Budi']);
    FamilyMember::factory()->create([
        'family_id' => $family->id,
        'user_id' => $target->id,
        'role' => 'admin',
    ]);

    $response = $this->getJson("/api/v1/admin/users/{$target->id}")->assertOk();

    $response->assertJsonPath('data.id', $target->id);
    $response->assertJsonPath('data.families.0.family_name', 'Keluarga Budi');
    $response->assertJsonPath('data.families.0.role', 'admin');
});

test('admin user list and detail never expose password_hash', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $target = User::factory()->create();

    $this->getJson('/api/v1/admin/users')->assertOk()->assertJsonMissingPath('data.0.password_hash');
    $this->getJson("/api/v1/admin/users/{$target->id}")->assertOk()->assertJsonMissingPath('data.password_hash');
});
