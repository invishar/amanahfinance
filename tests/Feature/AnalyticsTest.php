<?php

use App\Actions\Analytics\AnalyticsActions;
use App\Models\Account;
use App\Models\Family;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletBudget;
use App\Support\CurrentFamily;

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

test('summary includes income sources with zero realization this month', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $source = IncomeSource::factory()->for($family)->create(['name' => 'Gaji Bulanan', 'expected_amount' => 8_000_000]);

    $this->getJson('/api/v1/analytics/summary')
        ->assertOk()
        ->assertJsonFragment([
            'source_id' => $source->id,
            'name' => 'Gaji Bulanan',
            'expected' => 8_000_000,
            'actual' => 0,
        ]);
});

test('summary reports actual realization per income source', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();
    // IncomeSourceFactory draws name from a small fixed pool -- pin distinct
    // names, income_sources_family_id_name_unique would occasionally collide
    // otherwise (same class of flake as AccountTest's pagination test).
    $source = IncomeSource::factory()->for($family)->create(['name' => 'Gaji Utama', 'expected_amount' => 8_000_000]);
    $otherSource = IncomeSource::factory()->for($family)->create(['name' => 'Sumber Lain']);

    Transaction::factory()->for($family)->create([
        'type' => 'income', 'amount' => 3_000_000, 'account_id' => $account->id,
        'wallet_id' => null, 'source_id' => $source->id, 'transaction_date' => now(),
    ]);
    Transaction::factory()->for($family)->create([
        'type' => 'income', 'amount' => 4_500_000, 'account_id' => $account->id,
        'wallet_id' => null, 'source_id' => $source->id, 'transaction_date' => now(),
    ]);

    $response = $this->getJson('/api/v1/analytics/summary')->assertOk();

    $response->assertJsonFragment(['source_id' => $source->id, 'actual' => 7_500_000]);
    $response->assertJsonFragment(['source_id' => $otherSource->id, 'actual' => 0]);
});

test('income source with no expected_amount reports null, not zero', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $source = IncomeSource::factory()->for($family)->create(['expected_amount' => null]);

    $this->getJson('/api/v1/analytics/summary')
        ->assertOk()
        ->assertJsonFragment(['source_id' => $source->id, 'expected' => null]);
});

test('summary income_sources does not leak other familys data', function () {
    $this->actingAsFamilyMember('member');
    $otherSource = IncomeSource::factory()->for(Family::factory())->create();

    $response = $this->getJson('/api/v1/analytics/summary')->assertOk();

    $response->assertJsonMissing(['source_id' => $otherSource->id]);
});

test('invalid month format is rejected', function () {
    $this->actingAsFamilyMember('member');

    $this->getJson('/api/v1/analytics/summary?month=not-a-month')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['month']);
});

// Regresi: AssistantService memanggil AnalyticsActions::summary() dari dalam
// queue job -- tanpa request HTTP, jadi middleware ResolveFamily tidak pernah
// jalan dan CurrentFamily::id() null. Global scope BelongsToFamily fail-open
// pada kondisi itu (diam, bukan menolak), sehingga summary sempat mengembalikan
// wallet & income source SELURUH tabel dan membocorkannya ke system prompt LLM.
// Test lain di file ini semuanya lewat HTTP, tempat scope memang bekerja --
// karena itu kebocoran ini lolos. Yang ini sengaja TIDAK pakai actingAs.
test('summary tidak membocorkan data family lain saat dipanggil tanpa konteks request', function () {
    $family = Family::factory()->create();
    $wallet = Wallet::factory()->for($family)->create();
    $source = IncomeSource::factory()->for($family)->create();

    $otherFamily = Family::factory()->create();
    $otherWallet = Wallet::factory()->for($otherFamily)->create();
    $otherSource = IncomeSource::factory()->for($otherFamily)->create();

    expect(app(CurrentFamily::class)->id())->toBeNull();

    $summary = app(AnalyticsActions::class)
        ->summary($family->id, now()->startOfMonth());

    expect(collect($summary['wallets'])->pluck('wallet_id')->all())->toBe([$wallet->id]);
    expect(collect($summary['income_sources'])->pluck('source_id')->all())->toBe([$source->id]);

    $json = json_encode($summary);
    expect($json)->not->toContain($otherWallet->id);
    expect($json)->not->toContain($otherSource->id);
});
