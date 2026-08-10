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

test('tenant leak cannot view other familys goal', function () {
    $this->actingAsFamilyMember('admin');
    $other = SavingsGoal::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/savings-goals/'.$other->id)->assertStatus(404);
});
