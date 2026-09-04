<?php

use App\Models\Account;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\RecurringRule;
use App\Models\SavingsGoal;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Ai\FamilyFinancialData;

test('Amina reads balances goals transactions routines subscription and profile from the requested family', function () {
    $family = Family::factory()->create(['name' => 'Keluarga Aman']);
    $member = FamilyMember::factory()->for($family)->create(['nickname' => 'Bunda Aman']);
    $account = Account::factory()->for($family)->create([
        'name' => 'Rekening Aman',
        'current_balance' => 4_250_000,
    ]);
    $wallet = Wallet::factory()->for($family)->create(['name' => 'Belanja Aman']);
    SavingsGoal::factory()->for($family)->create([
        'target_name' => 'Dana Darurat Aman',
        'target_amount' => 10_000_000,
        'current_amount' => 2_500_000,
        'account_id' => $account->id,
    ]);
    Transaction::factory()->for($family)->create([
        'account_id' => $account->id,
        'wallet_id' => $wallet->id,
        'created_by' => $member->id,
        'note' => 'Belanja sekolah aman',
        'amount' => 175_000,
        'transaction_date' => now(),
    ]);
    RecurringRule::factory()->for($family)->create([
        'account_id' => $account->id,
        'wallet_id' => $wallet->id,
        'note' => 'Internet rumah aman',
    ]);
    $plan = SubscriptionPlan::factory()->create(['name' => 'Paket Aman']);
    Subscription::factory()->for($family)->for($plan, 'plan')->active()->create();

    $reader = app(FamilyFinancialData::class);

    expect($reader->read($family, 'accounts')[0])
        ->name->toBe('Rekening Aman')
        ->balance->toBe(4_250_000);
    expect($reader->read($family, 'savings_goals')[0])->toMatchArray([
        'name' => 'Dana Darurat Aman',
        'current' => 2_500_000,
        'remaining' => 7_500_000,
        'percent' => 25,
    ]);
    expect($reader->read($family, 'recent_transactions')[0])->toMatchArray([
        'amount' => 175_000,
        'account' => 'Rekening Aman',
        'wallet' => 'Belanja Aman',
        'note' => 'Belanja sekolah aman',
    ]);
    expect($reader->read($family, 'recurring_rules')[0])->toMatchArray([
        'account' => 'Rekening Aman',
        'wallet' => 'Belanja Aman',
        'note' => 'Internet rumah aman',
    ]);
    expect($reader->read($family, 'subscription'))->toMatchArray([
        'plan' => 'Paket Aman',
        'status' => 'active',
    ]);
    $profile = $reader->read($family, 'family_profile');
    expect($profile['family_name'])->toBe('Keluarga Aman');
    expect($profile['members'][0]['name'])->toBe('Bunda Aman');
});

test('every Amina data topic excludes another family', function () {
    $family = Family::factory()->create(['name' => 'Keluarga Sendiri']);
    FamilyMember::factory()->for($family)->create(['nickname' => 'Anggota Sendiri']);
    Account::factory()->for($family)->create(['name' => 'Akun Sendiri']);

    $other = Family::factory()->create(['name' => 'Keluarga Rahasia']);
    $otherMember = FamilyMember::factory()->for($other)->create(['nickname' => 'Anggota Rahasia']);
    $otherAccount = Account::factory()->for($other)->create(['name' => 'Akun Rahasia']);
    $otherWallet = Wallet::factory()->for($other)->create(['name' => 'Wallet Rahasia']);
    SavingsGoal::factory()->for($other)->create(['target_name' => 'Target Rahasia']);
    Transaction::factory()->for($other)->create([
        'account_id' => $otherAccount->id,
        'wallet_id' => $otherWallet->id,
        'created_by' => $otherMember->id,
        'note' => 'Transaksi Rahasia',
    ]);
    RecurringRule::factory()->for($other)->create([
        'account_id' => $otherAccount->id,
        'wallet_id' => $otherWallet->id,
        'note' => 'Rutin Rahasia',
    ]);
    Subscription::factory()->for($other)->create();

    $reader = app(FamilyFinancialData::class);
    $allResults = collect([
        'summary',
        'accounts',
        'savings_goals',
        'recent_transactions',
        'recurring_rules',
        'subscription',
        'family_profile',
    ])->mapWithKeys(fn (string $topic) => [$topic => $reader->read($family, $topic)]);
    $json = json_encode($allResults, JSON_UNESCAPED_UNICODE);

    expect($json)
        ->toContain('Keluarga Sendiri')
        ->toContain('Akun Sendiri')
        ->not->toContain('Rahasia');
});
