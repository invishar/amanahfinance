<?php

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
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

test('admin sees the family\'s newest subscription status and expiry in list and detail', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $target = User::factory()->create(['full_name' => 'Subscriber One']);
    $family = Family::factory()->create(['name' => 'Keluarga Budi']);
    FamilyMember::factory()->create([
        'family_id' => $family->id,
        'user_id' => $target->id,
        'role' => 'admin',
        'joined_at' => now(),
    ]);

    $plan = SubscriptionPlan::factory()->create(['name' => 'Paket Tahunan']);

    // Baris lama (rejected) lalu baris baru (active) -- yang tampil harus yang terbaru.
    Subscription::factory()->rejected()->create(['family_id' => $family->id, 'created_at' => now()->subDays(10)]);
    $latest = Subscription::factory()->active()->create([
        'family_id' => $family->id,
        'plan_id' => $plan->id,
        'created_at' => now(),
    ]);

    $list = $this->getJson('/api/v1/admin/users?search=Subscriber')->assertOk();
    $list->assertJsonPath('data.0.full_name', 'Subscriber One');
    $list->assertJsonPath('data.0.subscription_status', 'active');
    $list->assertJsonPath('data.0.subscription_plan_name', 'Paket Tahunan');
    $list->assertJsonPath('data.0.subscription_expires_at', $latest->ends_at->toJSON());

    $detail = $this->getJson("/api/v1/admin/users/{$target->id}")->assertOk();
    $detail->assertJsonPath('data.families.0.subscription_status', 'active');
    $detail->assertJsonPath('data.families.0.subscription_plan_name', 'Paket Tahunan');
    $detail->assertJsonPath('data.families.0.subscription_expires_at', $latest->ends_at->toJSON());
});

test('subscription status is null when the family never requested a plan', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $target = User::factory()->create();
    $family = Family::factory()->create();
    FamilyMember::factory()->create(['family_id' => $family->id, 'user_id' => $target->id]);

    $response = $this->getJson("/api/v1/admin/users/{$target->id}")->assertOk();

    $response->assertJsonPath('data.families.0.subscription_status', null);
    $response->assertJsonPath('data.families.0.subscription_plan_name', null);
    $response->assertJsonPath('data.families.0.subscription_expires_at', null);
});

test('admin can filter users by subscription status', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

    $activeUser = User::factory()->create(['full_name' => 'Active User']);
    $activeFamily = Family::factory()->create();
    FamilyMember::factory()->create(['family_id' => $activeFamily->id, 'user_id' => $activeUser->id, 'joined_at' => now()]);
    Subscription::factory()->active()->create(['family_id' => $activeFamily->id]);

    $rejectedUser = User::factory()->create(['full_name' => 'Rejected User']);
    $rejectedFamily = Family::factory()->create();
    FamilyMember::factory()->create(['family_id' => $rejectedFamily->id, 'user_id' => $rejectedUser->id, 'joined_at' => now()]);
    Subscription::factory()->rejected()->create(['family_id' => $rejectedFamily->id]);

    $noFamilyUser = User::factory()->create(['full_name' => 'No Family User']);

    $noSubscriptionUser = User::factory()->create(['full_name' => 'No Subscription User']);
    $bareFamily = Family::factory()->create();
    FamilyMember::factory()->create(['family_id' => $bareFamily->id, 'user_id' => $noSubscriptionUser->id, 'joined_at' => now()]);

    $activeResponse = $this->getJson('/api/v1/admin/users?subscription_status=active')->assertOk();
    expect($activeResponse->json('data'))->toHaveCount(1);
    $activeResponse->assertJsonPath('data.0.full_name', 'Active User');

    $rejectedResponse = $this->getJson('/api/v1/admin/users?subscription_status=rejected')->assertOk();
    expect($rejectedResponse->json('data'))->toHaveCount(1);
    $rejectedResponse->assertJsonPath('data.0.full_name', 'Rejected User');

    $noneResponse = $this->getJson('/api/v1/admin/users?subscription_status=none')->assertOk();
    $noneNames = collect($noneResponse->json('data'))->pluck('full_name');
    expect($noneNames)->toContain('No Family User', 'No Subscription User');
    expect($noneNames)->not->toContain('Active User', 'Rejected User');
});

test('invalid subscription_status filter is rejected', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

    $this->getJson('/api/v1/admin/users?subscription_status=bogus')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['subscription_status']);
});

test('admin user list and detail never expose password_hash', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $target = User::factory()->create();

    $this->getJson('/api/v1/admin/users')->assertOk()->assertJsonMissingPath('data.0.password_hash');
    $this->getJson("/api/v1/admin/users/{$target->id}")->assertOk()->assertJsonMissingPath('data.password_hash');
});
