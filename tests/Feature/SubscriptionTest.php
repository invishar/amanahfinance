<?php

use App\Models\Family;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('member can select a plan and submit payment confirmation', function () {
    [, , $member] = $this->actingAsFamilyMember('member');
    $plan = SubscriptionPlan::factory()->create(['price' => 49_000, 'duration_days' => 30]);

    $response = $this->postJson('/api/v1/subscriptions', [
        'plan_id' => $plan->id,
        'payment_note' => 'Transfer BCA a/n Budi, ref 12345',
    ])->assertCreated();

    $response->assertJsonPath('data.status', 'pending_payment');
    $response->assertJsonPath('data.amount', 49_000);
    $response->assertJsonPath('data.plan_id', $plan->id);
    $response->assertJsonPath('data.requested_by', $member->id);
    $this->assertNotNull($response->json('data.paid_at'));
});

test('payment proof url from POST /uploads is stored with the request', function () {
    $this->actingAsFamilyMember('member');
    $plan = SubscriptionPlan::factory()->create();

    $response = $this->postJson('/api/v1/subscriptions', [
        'plan_id' => $plan->id,
        'payment_proof_url' => 'https://example.test/storage/uploads/fam-1/bukti.jpg',
    ])->assertCreated();

    $response->assertJsonPath('data.payment_proof_url', 'https://example.test/storage/uploads/fam-1/bukti.jpg');
});

test('viewer cannot select a plan', function () {
    $this->actingAsFamilyMember('viewer');
    $plan = SubscriptionPlan::factory()->create();

    $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id])->assertStatus(403);
});

test('selecting an inactive plan is rejected', function () {
    $this->actingAsFamilyMember('member');
    $plan = SubscriptionPlan::factory()->inactive()->create();

    $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan_id']);
});

test('a family cannot submit a second request while one is pending or active', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $plan = SubscriptionPlan::factory()->create();
    Subscription::factory()->for($family)->create(['plan_id' => $plan->id, 'status' => 'pending_payment']);

    $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id])->assertStatus(409);
});

test('tenant leak cannot view other familys subscription', function () {
    $this->actingAsFamilyMember('admin');
    $other = Subscription::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/subscriptions/'.$other->id)->assertStatus(404);
});

test('non platform admin cannot access the admin review queue', function () {
    $this->actingAsFamilyMember('admin');

    $this->getJson('/api/v1/admin/subscriptions')->assertStatus(403);
});

test('platform admin sees pending requests across every family', function () {
    Subscription::factory()->for(Family::factory())->create(['status' => 'pending_payment']);
    Subscription::factory()->for(Family::factory())->create(['status' => 'pending_payment']);
    Subscription::factory()->for(Family::factory())->active()->create();

    Sanctum::actingAs(User::factory()->create(['is_platform_admin' => true]));

    $this->getJson('/api/v1/admin/subscriptions?status=pending_payment')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('platform admin can activate a pending subscription', function () {
    $plan = SubscriptionPlan::factory()->create(['duration_days' => 30]);
    $subscription = Subscription::factory()->for(Family::factory())->create([
        'plan_id' => $plan->id,
        'status' => 'pending_payment',
    ]);
    $admin = User::factory()->create(['is_platform_admin' => true]);
    Sanctum::actingAs($admin);

    $response = $this->postJson("/api/v1/admin/subscriptions/{$subscription->id}/activate")
        ->assertOk();

    $response->assertJsonPath('data.status', 'active');
    $response->assertJsonPath('data.reviewed_by', $admin->id);

    $subscription->refresh();
    $this->assertNotNull($subscription->starts_at);
    $this->assertEqualsWithDelta(
        $subscription->starts_at->addDays(30)->timestamp,
        $subscription->ends_at->timestamp,
        1
    );
});

test('platform admin can reject a pending subscription with a note', function () {
    $subscription = Subscription::factory()->for(Family::factory())->create(['status' => 'pending_payment']);
    $admin = User::factory()->create(['is_platform_admin' => true]);
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/subscriptions/{$subscription->id}/reject", [
        'review_note' => 'Bukti transfer tidak jelas',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.review_note', 'Bukti transfer tidak jelas');
});

test('rejecting requires a note', function () {
    $subscription = Subscription::factory()->for(Family::factory())->create(['status' => 'pending_payment']);
    Sanctum::actingAs(User::factory()->create(['is_platform_admin' => true]));

    $this->postJson("/api/v1/admin/subscriptions/{$subscription->id}/reject", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['review_note']);
});

test('activating an already resolved subscription fails with 422', function () {
    $subscription = Subscription::factory()->for(Family::factory())->active()->create();
    Sanctum::actingAs(User::factory()->create(['is_platform_admin' => true]));

    $this->postJson("/api/v1/admin/subscriptions/{$subscription->id}/activate")
        ->assertStatus(422);
});

test('non platform admin cannot activate or reject', function () {
    $subscription = Subscription::factory()->for(Family::factory())->create(['status' => 'pending_payment']);
    $this->actingAsFamilyMember('admin');

    $this->postJson("/api/v1/admin/subscriptions/{$subscription->id}/activate")->assertStatus(403);
    $this->postJson("/api/v1/admin/subscriptions/{$subscription->id}/reject", ['review_note' => 'x'])->assertStatus(403);
});

test('daily command expires subscriptions past their end date', function () {
    $expired = Subscription::factory()->for(Family::factory())->create([
        'status' => 'active',
        'starts_at' => now()->subDays(40),
        'ends_at' => now()->subDay(),
    ]);
    $stillActive = Subscription::factory()->for(Family::factory())->create([
        'status' => 'active',
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);
    $pending = Subscription::factory()->for(Family::factory())->create(['status' => 'pending_payment']);

    $this->artisan('amana:expire-subscriptions')->assertExitCode(0);

    expect($expired->fresh()->status)->toBe('expired');
    expect($stillActive->fresh()->status)->toBe('active');
    expect($pending->fresh()->status)->toBe('pending_payment');
});
