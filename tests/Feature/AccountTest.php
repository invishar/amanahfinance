<?php

use App\Models\Account;
use App\Models\Family;
use App\Models\IncomeSource;
use App\Models\Transaction;

test('store sets current balance to opening balance', function () {
    $this->actingAsFamilyMember('member');

    $response = $this->postJson('/api/v1/accounts', [
        'name' => 'BCA',
        'account_type' => 'bank',
        'opening_balance' => 500_000,
    ])->assertCreated();

    $response->assertJsonPath('data.opening_balance', 500_000);
    $response->assertJsonPath('data.current_balance', 500_000);
});

test('viewer cannot create account', function () {
    $this->actingAsFamilyMember('viewer');

    $this->postJson('/api/v1/accounts', ['name' => 'BCA', 'account_type' => 'bank'])
        ->assertStatus(403);
});

test('update cannot change balances directly', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    $account = Account::factory()->for($family)->create(['current_balance' => 100_000, 'opening_balance' => 100_000]);

    $this->putJson('/api/v1/accounts/'.$account->id, [
        'name' => 'BCA Utama',
        'current_balance' => 999_999_999,
    ])->assertOk();

    $account->refresh();
    $this->assertSame('BCA Utama', $account->name);
    $this->assertSame(100_000, $account->current_balance);
});

test('delete blocked with 409 when referenced by transaction', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    $account = Account::factory()->for($family)->create();
    Transaction::factory()->for($family)->create(['account_id' => $account->id, 'wallet_id' => null, 'type' => 'income', 'source_id' => IncomeSource::factory()->for($family)]);

    $this->deleteJson('/api/v1/accounts/'.$account->id)->assertStatus(409);
    $this->assertDatabaseHas('accounts', ['id' => $account->id]);
});

test('tenant leak cannot view other familys account', function () {
    $this->actingAsFamilyMember('admin');
    $otherAccount = Account::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/accounts/'.$otherAccount->id)->assertStatus(404);
});

test('validation errors are localized indonesian sentences, not raw keys', function () {
    $this->actingAsFamilyMember('member');

    $response = $this->postJson('/api/v1/accounts', ['account_type' => 'bank'])
        ->assertStatus(422);

    $message = $response->json('errors.name.0');
    expect($message)->toBe('Nama wajib diisi.');
    expect($response->json('message'))->toBe('Nama wajib diisi.');
    expect($message)->not->toContain('validation.');
});

test('pagination link labels are localized', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    // AccountFactory draws names from a small fixed pool per account_type
    // (accounts_family_id_name_unique would collide well before 25) --
    // force distinct names since this test only cares about pagination.
    Account::factory()->for($family)->count(25)->sequence(fn ($seq) => ['name' => 'Akun '.$seq->index])->create();

    $response = $this->getJson('/api/v1/accounts')->assertOk();

    $labels = collect($response->json('meta.links'))->pluck('label');
    expect($labels)->toContain('Berikutnya');
    expect($labels->contains(fn ($label) => str_contains((string) $label, 'pagination.')))->toBeFalse();
});
