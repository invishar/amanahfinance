<?php

use App\Models\Family;
use App\Models\IncomeSource;
use App\Models\RecurringRule;
use App\Models\Wallet;

test('expense requires wallet id', function () {
    $this->actingAsFamilyMember('member');

    $this->postJson('/api/v1/recurring-rules', [
        'type' => 'expense',
        'amount' => 100_000,
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=1',
        'next_run_on' => now()->addMonth()->toDateString(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['wallet_id']);
});

test('store creates recurring expense', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    $wallet = Wallet::factory()->for($family)->create();

    $this->postJson('/api/v1/recurring-rules', [
        'type' => 'expense',
        'amount' => 150_000,
        'wallet_id' => $wallet->id,
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=1',
        'next_run_on' => now()->addMonth()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_active', true);
});

test('changing type clears stale foreign keys', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    $wallet = Wallet::factory()->for($family)->create();
    $source = IncomeSource::factory()->for($family)->create();
    $rule = RecurringRule::factory()->for($family)->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'source_id' => null]);

    $response = $this->putJson('/api/v1/recurring-rules/'.$rule->id, [
        'type' => 'income',
        'source_id' => $source->id,
    ])->assertOk();

    $response->assertJsonPath('data.wallet_id', null);
    $response->assertJsonPath('data.source_id', $source->id);
});

test('tenant leak cannot view other familys rule', function () {
    $this->actingAsFamilyMember('admin');
    $other = RecurringRule::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/recurring-rules/'.$other->id)->assertStatus(404);
});
