<?php

use App\Models\Family;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('catalog is browsable without any authentication', function () {
    SubscriptionPlan::factory()->create(['code' => 'bulanan', 'is_active' => true]);
    SubscriptionPlan::factory()->create(['code' => 'nonaktif', 'is_active' => false]);

    $this->getJson('/api/v1/subscription-plans?is_active=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'bulanan');
});

test('a single plan is viewable without any authentication', function () {
    $plan = SubscriptionPlan::factory()->create();

    $this->getJson("/api/v1/subscription-plans/{$plan->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $plan->id);
});

test('unauthenticated request cannot create a plan', function () {
    $this->postJson('/api/v1/subscription-plans', [
        'code' => 'bulanan',
        'name' => 'Paket Bulanan',
        'price' => 49_000,
        'duration_days' => 30,
    ])->assertStatus(401);
});

test('non platform admin cannot create a plan', function () {
    $this->actingAsFamilyMember('admin');

    $this->postJson('/api/v1/subscription-plans', [
        'code' => 'bulanan',
        'name' => 'Paket Bulanan',
        'price' => 49_000,
        'duration_days' => 30,
    ])->assertStatus(403);
});

test('platform admin can create update and delete a plan', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

    $created = $this->postJson('/api/v1/subscription-plans', [
        'code' => 'bulanan',
        'name' => 'Paket Bulanan',
        'price' => 49_000,
        'duration_days' => 30,
    ])->assertCreated();

    $planId = $created->json('data.id');

    $this->putJson("/api/v1/subscription-plans/{$planId}", ['price' => 59_000])
        ->assertOk()
        ->assertJsonPath('data.price', 59_000);

    $this->deleteJson("/api/v1/subscription-plans/{$planId}")->assertStatus(204);
    $this->assertDatabaseCount('subscription_plans', 0);
});

test('deleting a plan referenced by a subscription is blocked with 409', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $plan = SubscriptionPlan::factory()->create();
    Subscription::factory()->for(Family::factory())->create(['plan_id' => $plan->id]);

    $this->deleteJson("/api/v1/subscription-plans/{$plan->id}")->assertStatus(409);
});
