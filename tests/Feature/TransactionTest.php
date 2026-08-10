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

test('tenant leak cannot view other familys transaction', function () {
    $this->actingAsFamilyMember('admin');
    $other = Transaction::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/transactions/'.$other->id)->assertStatus(404);
});
