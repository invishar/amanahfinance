<?php

use App\Models\Account;
use App\Models\Family;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\Wallet;

test('expense requires wallet id', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();

    $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => 10_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $account->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['wallet_id']);
});

test('income requires source id', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();

    $this->postJson('/api/v1/transactions', [
        'type' => 'income',
        'amount' => 10_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $account->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['source_id']);
});

test('transfer requires different to account id', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();

    $this->postJson('/api/v1/transactions', [
        'type' => 'transfer',
        'amount' => 10_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $account->id,
        'to_account_id' => $account->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to_account_id']);
});

test('savings requires goal id', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();

    $this->postJson('/api/v1/transactions', [
        'type' => 'savings',
        'amount' => 10_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $account->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['goal_id']);
});

test('expense decreases account balance', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create(['opening_balance' => 100_000, 'current_balance' => 100_000]);
    $wallet = Wallet::factory()->for($family)->create();

    $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => 30_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $account->id,
        'wallet_id' => $wallet->id,
    ])->assertCreated();

    $this->assertSame(70_000, $account->fresh()->current_balance);
});

test('transfer moves balance between accounts', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $from = Account::factory()->for($family)->create(['current_balance' => 100_000]);
    $to = Account::factory()->for($family)->create(['current_balance' => 0]);

    $this->postJson('/api/v1/transactions', [
        'type' => 'transfer',
        'amount' => 40_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $from->id,
        'to_account_id' => $to->id,
    ])->assertCreated();

    $this->assertSame(60_000, $from->fresh()->current_balance);
    $this->assertSame(40_000, $to->fresh()->current_balance);
});

test('savings moves money into goal', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create(['current_balance' => 100_000]);
    $goal = SavingsGoal::factory()->for($family)->create(['current_amount' => 0, 'target_amount' => 500_000]);

    $this->postJson('/api/v1/transactions', [
        'type' => 'savings',
        'amount' => 25_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $account->id,
        'goal_id' => $goal->id,
    ])->assertCreated();

    $this->assertSame(75_000, $account->fresh()->current_balance);
    $this->assertSame(25_000, $goal->fresh()->current_amount);
});

test('update reverses old effect and applies new amount', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create(['current_balance' => 100_000]);
    $wallet = Wallet::factory()->for($family)->create();

    $create = $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => 30_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $account->id,
        'wallet_id' => $wallet->id,
    ])->assertCreated();

    $this->assertSame(70_000, $account->fresh()->current_balance);

    $id = $create->json('data.id');
    $this->putJson("/api/v1/transactions/{$id}", ['amount' => 50_000])->assertOk();

    $this->assertSame(50_000, $account->fresh()->current_balance);
});

test('delete reverses effect and soft deletes', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create(['current_balance' => 100_000]);
    $wallet = Wallet::factory()->for($family)->create();

    $create = $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => 30_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $account->id,
        'wallet_id' => $wallet->id,
    ])->assertCreated();

    $id = $create->json('data.id');
    $this->deleteJson("/api/v1/transactions/{$id}")->assertNoContent();

    $this->assertSame(100_000, $account->fresh()->current_balance);
    $this->assertSoftDeleted('transactions', ['id' => $id]);
});

test('origin and created by are never client writable', function () {
    [$user, $family, $member] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();
    $wallet = Wallet::factory()->for($family)->create();

    $response = $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => 10_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $account->id,
        'wallet_id' => $wallet->id,
        'origin' => 'receipt_ocr',
        'created_by' => 'not-a-real-member-id',
    ])->assertCreated();

    $response->assertJsonPath('data.origin', 'manual');
    $response->assertJsonPath('data.created_by', $member->id);
});

test('viewer cannot create transaction', function () {
    [, $family] = $this->actingAsFamilyMember('viewer');
    $account = Account::factory()->for($family)->create();
    $wallet = Wallet::factory()->for($family)->create();

    $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => 10_000,
        'transaction_date' => now()->toDateString(),
        'account_id' => $account->id,
        'wallet_id' => $wallet->id,
    ])->assertStatus(403);
});

test('index defaults to transaction_date desc then created_at desc', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $older = Transaction::factory()->for($family)->create(['transaction_date' => '2026-08-01', 'created_at' => now()->subHour()]);
    $sameDayLater = Transaction::factory()->for($family)->create(['transaction_date' => '2026-08-05', 'created_at' => now()]);
    $sameDayEarlier = Transaction::factory()->for($family)->create(['transaction_date' => '2026-08-05', 'created_at' => now()->subMinutes(10)]);

    $ids = $this->getJson('/api/v1/transactions')->assertOk()->json('data.*.id');

    expect($ids)->toBe([$sameDayLater->id, $sameDayEarlier->id, $older->id]);
});

test('index filters by month', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $inMonth = Transaction::factory()->for($family)->create(['transaction_date' => '2026-08-15']);
    Transaction::factory()->for($family)->create(['transaction_date' => '2026-07-15']);

    $ids = $this->getJson('/api/v1/transactions?month=2026-08')->assertOk()->json('data.*.id');

    expect($ids)->toBe([$inMonth->id]);
});

test('index rejects a malformed month', function () {
    $this->actingAsFamilyMember('member');

    $this->getJson('/api/v1/transactions?month=not-a-month')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['month']);
});

test('index filters by wallet, account, and type', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $wallet = Wallet::factory()->for($family)->create();
    $account = Account::factory()->for($family)->create();
    $matching = Transaction::factory()->for($family)->create(['wallet_id' => $wallet->id, 'account_id' => $account->id, 'type' => 'expense']);
    Transaction::factory()->for($family)->create();
    Transaction::factory()->for($family)->income()->create(['account_id' => $account->id]);

    $ids = $this->getJson("/api/v1/transactions?wallet_id={$wallet->id}")->assertOk()->json('data.*.id');
    expect($ids)->toBe([$matching->id]);

    $ids = $this->getJson('/api/v1/transactions?type=income')->assertOk()->json('data.*.id');
    expect($ids)->toHaveCount(1);

    $ids = $this->getJson("/api/v1/transactions?account_id={$account->id}&type=expense")->assertOk()->json('data.*.id');
    expect($ids)->toBe([$matching->id]);
});

test('index account_id filter does not match to_account_id on transfers', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();
    Transaction::factory()->for($family)->transfer()->create(['to_account_id' => $account->id]);

    $ids = $this->getJson("/api/v1/transactions?account_id={$account->id}")->assertOk()->json('data.*.id');

    expect($ids)->toBeEmpty();
});

test('index respects per_page up to the 100 cap', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    Transaction::factory()->for($family)->count(5)->create();

    $this->getJson('/api/v1/transactions?per_page=2')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/transactions?per_page=500')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['per_page']);
});

test('tenant leak cannot view other familys transaction', function () {
    $this->actingAsFamilyMember('admin');
    $other = Transaction::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/transactions/'.$other->id)->assertStatus(404);
});
