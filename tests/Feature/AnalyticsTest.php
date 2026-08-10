<?php

use App\Models\Account;
use App\Models\Family;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletBudget;

test('summary reports cashflow totals for the current month', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();
    $source = IncomeSource::factory()->for($family)->create();
    $wallet = Wallet::factory()->for($family)->create();

    Transaction::factory()->for($family)->create([
        'type' => 'income', 'amount' => 5_000_000, 'account_id' => $account->id,
        'wallet_id' => null, 'source_id' => $source->id, 'transaction_date' => now(),
    ]);
    Transaction::factory()->for($family)->create([
        'type' => 'expense', 'amount' => 1_200_000, 'account_id' => $account->id,
        'wallet_id' => $wallet->id, 'transaction_date' => now(),
    ]);

    $this->getJson('/api/v1/analytics/summary')
        ->assertOk()
        ->assertJsonPath('data.cashflow.total_income', 5_000_000)
        ->assertJsonPath('data.cashflow.total_expense', 1_200_000)
        ->assertJsonPath('data.cashflow.net', 3_800_000);
});

test('summary includes wallets with zero spend this month', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $wallet = Wallet::factory()->for($family)->create(['monthly_budget' => 500_000]);

    $this->getJson('/api/v1/analytics/summary')
        ->assertOk()
        ->assertJsonFragment([
            'wallet_id' => $wallet->id,
            'budget' => 500_000,
            'spent' => 0,
            'status' => 'ok',
        ]);
});

test('summary uses this months wallet budget override instead of monthly_budget', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $wallet = Wallet::factory()->for($family)->create(['monthly_budget' => 500_000]);
    WalletBudget::factory()->for($wallet)->create(['period' => now()->startOfMonth(), 'amount' => 750_000]);

    $this->getJson('/api/v1/analytics/summary')
        ->assertOk()
        ->assertJsonFragment(['wallet_id' => $wallet->id, 'budget' => 750_000]);
});

test('summary status reflects over budget', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();
    $wallet = Wallet::factory()->for($family)->create(['monthly_budget' => 100_000]);

    Transaction::factory()->for($family)->create([
        'type' => 'expense', 'amount' => 150_000, 'account_id' => $account->id,
        'wallet_id' => $wallet->id, 'transaction_date' => now(),
    ]);

    $this->getJson('/api/v1/analytics/summary')
        ->assertOk()
        ->assertJsonFragment(['wallet_id' => $wallet->id, 'spent' => 150_000, 'status' => 'over']);
});

test('summary does not leak other familys data', function () {
    $this->actingAsFamilyMember('member');
    $otherFamily = Family::factory()->create();
    $otherAccount = Account::factory()->for($otherFamily)->create();
    $otherWallet = Wallet::factory()->for($otherFamily)->create();
    Transaction::factory()->for($otherFamily)->create([
        'type' => 'expense', 'amount' => 999_000, 'account_id' => $otherAccount->id,
        'wallet_id' => $otherWallet->id, 'transaction_date' => now(),
    ]);

    $response = $this->getJson('/api/v1/analytics/summary')
        ->assertOk()
        ->assertJsonPath('data.cashflow.total_expense', 0);

    $response->assertJsonMissing(['wallet_id' => $otherWallet->id]);
});

test('month query param selects a different month', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();
    $source = IncomeSource::factory()->for($family)->create();

    Transaction::factory()->for($family)->create([
        'type' => 'income', 'amount' => 2_000_000, 'account_id' => $account->id,
        'wallet_id' => null, 'source_id' => $source->id,
        'transaction_date' => now()->subMonthNoOverflow(),
    ]);

    $month = now()->subMonthNoOverflow()->format('Y-m');

    $this->getJson("/api/v1/analytics/summary?month={$month}")
        ->assertOk()
        ->assertJsonPath('data.cashflow.total_income', 2_000_000);

    $this->getJson('/api/v1/analytics/summary')
        ->assertOk()
        ->assertJsonPath('data.cashflow.total_income', 0);
});

test('invalid month format is rejected', function () {
    $this->actingAsFamilyMember('member');

    $this->getJson('/api/v1/analytics/summary?month=not-a-month')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['month']);
});
