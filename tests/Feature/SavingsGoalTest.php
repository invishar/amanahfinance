<?php

use App\Models\Family;
use App\Models\SavingsGoal;
use App\Models\Transaction;

test('current amount is not client writable', function () {
    $this->actingAsFamilyMember('member');

    $response = $this->postJson('/api/v1/savings-goals', [
        'target_name' => 'Dana Darurat',
        'target_amount' => 10_000_000,
        'current_amount' => 9_999_999,
    ])->assertCreated();

    $response->assertJsonPath('data.current_amount', 0);
});

test('setting status to achieved stamps achieved at', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    $goal = SavingsGoal::factory()->for($family)->create(['status' => 'active', 'achieved_at' => null]);

    $response = $this->putJson('/api/v1/savings-goals/'.$goal->id, ['status' => 'achieved'])
        ->assertOk();

    $this->assertNotNull($response->json('data.achieved_at'));
});

test('delete blocked with 409 when referenced by transaction', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    $goal = SavingsGoal::factory()->for($family)->create();
    Transaction::factory()->savings()->for($family)->create(['goal_id' => $goal->id]);

    $this->deleteJson('/api/v1/savings-goals/'.$goal->id)->assertStatus(409);
});

test('eta is null without any contribution history', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $goal = SavingsGoal::factory()->for($family)->create(['current_amount' => 0, 'target_amount' => 5_000_000]);

    $this->getJson('/api/v1/savings-goals/'.$goal->id)
        ->assertOk()
        ->assertJsonPath('data.eta', null);
});

test('eta is null once the goal is already funded', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $goal = SavingsGoal::factory()->for($family)->create(['current_amount' => 5_000_000, 'target_amount' => 5_000_000]);

    $this->getJson('/api/v1/savings-goals/'.$goal->id)
        ->assertOk()
        ->assertJsonPath('data.eta', null);
});

test('eta is null for a paused goal even with contribution history', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $goal = SavingsGoal::factory()->for($family)->create(['status' => 'paused', 'current_amount' => 1_000_000, 'target_amount' => 5_000_000]);
    Transaction::factory()->savings()->for($family)->create(['goal_id' => $goal->id, 'transaction_date' => now()->subMonth()]);

    $this->getJson('/api/v1/savings-goals/'.$goal->id)
        ->assertOk()
        ->assertJsonPath('data.eta', null);
});

test('eta projects linearly from the average monthly contribution', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $now = now();
    $goal = SavingsGoal::factory()->for($family)->create(['current_amount' => 3_000_000, 'target_amount' => 6_000_000]);
    Transaction::factory()->savings()->for($family)->create([
        'goal_id' => $goal->id,
        'transaction_date' => $now->copy()->subMonths(2),
    ]);

    // 3 bulan berjalan (subMonths(2) + bulan berjalan ini), rata-rata 1jt/bulan,
    // sisa 3jt -> 3 bulan lagi.
    $expectedEta = $now->copy()->addMonthsNoOverflow(3)->startOfMonth()->toDateString();

    $this->getJson('/api/v1/savings-goals/'.$goal->id)
        ->assertOk()
        ->assertJsonPath('data.eta', $expectedEta);
});

test('index filters by status', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $achieved = SavingsGoal::factory()->achieved()->for($family)->create();
    SavingsGoal::factory()->for($family)->create(['status' => 'active']);

    $ids = $this->getJson('/api/v1/savings-goals?status=achieved')->assertOk()->json('data.*.id');

    expect($ids)->toBe([$achieved->id]);
});

test('index rejects an invalid status filter', function () {
    $this->actingAsFamilyMember('member');

    $this->getJson('/api/v1/savings-goals?status=whatever')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('tenant leak cannot view other familys goal', function () {
    $this->actingAsFamilyMember('admin');
    $other = SavingsGoal::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/savings-goals/'.$other->id)->assertStatus(404);
});
