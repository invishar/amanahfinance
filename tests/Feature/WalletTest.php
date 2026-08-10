<?php

use App\Models\Family;
use App\Models\Wallet;

test('member can create wallet', function () {
    $this->actingAsFamilyMember('member');

    $this->postJson('/api/v1/wallets', ['name' => 'Makan'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Makan')
        ->assertJsonPath('data.monthly_budget', 0);
});

test('name must be unique within family', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    Wallet::factory()->for($family)->create(['name' => 'Makan']);

    $this->postJson('/api/v1/wallets', ['name' => 'Makan'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('same name allowed in a different family', function () {
    Wallet::factory()->for(Family::factory())->create(['name' => 'Makan']);
    $this->actingAsFamilyMember('admin');

    $this->postJson('/api/v1/wallets', ['name' => 'Makan'])->assertCreated();
});

test('only admin can delete', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $wallet = Wallet::factory()->for($family)->create();

    $this->deleteJson('/api/v1/wallets/'.$wallet->id)->assertStatus(403);
});

test('tenant leak cannot view other familys wallet', function () {
    $this->actingAsFamilyMember('admin');
    $otherWallet = Wallet::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/wallets/'.$otherWallet->id)->assertStatus(404);
});
