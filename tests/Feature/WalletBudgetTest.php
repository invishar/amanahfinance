<?php

use App\Models\Family;
use App\Models\Wallet;
use App\Models\WalletBudget;

test('period is normalized to first of month', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    $wallet = Wallet::factory()->for($family)->create();

    $this->postJson("/api/v1/wallets/{$wallet->id}/budgets", [
        'period' => '2026-03-17',
        'amount' => 1_000_000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.period', '2026-03-01');
});

test('cannot create budget for another familys wallet', function () {
    $this->actingAsFamilyMember('admin');
    $otherWallet = Wallet::factory()->for(Family::factory())->create();

    $this->postJson("/api/v1/wallets/{$otherWallet->id}/budgets", [
        'period' => '2026-03-01',
        'amount' => 1_000_000,
    ])->assertStatus(404);
});

test('tenant leak on shallow show route', function () {
    $this->actingAsFamilyMember('admin');
    $otherWallet = Wallet::factory()->for(Family::factory())->create();
    $otherBudget = WalletBudget::factory()->for($otherWallet)->create();

    $this->getJson('/api/v1/budgets/'.$otherBudget->id)->assertStatus(403);
});
