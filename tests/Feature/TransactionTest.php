<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Family;
use App\Models\IncomeSource;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_expense_requires_wallet_id(): void
    {
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
    }

    public function test_income_requires_source_id(): void
    {
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
    }

    public function test_transfer_requires_different_to_account_id(): void
    {
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
    }

    public function test_savings_requires_goal_id(): void
    {
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
    }

    public function test_expense_decreases_account_balance(): void
    {
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
    }

    public function test_transfer_moves_balance_between_accounts(): void
    {
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
    }

    public function test_savings_moves_money_into_goal(): void
    {
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
    }

    public function test_update_reverses_old_effect_and_applies_new_amount(): void
    {
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
    }

    public function test_delete_reverses_effect_and_soft_deletes(): void
    {
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
    }

    public function test_origin_and_created_by_are_never_client_writable(): void
    {
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
    }

    public function test_viewer_cannot_create_transaction(): void
    {
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
    }

    public function test_tenant_leak_cannot_view_other_familys_transaction(): void
    {
        $this->actingAsFamilyMember('admin');
        $other = Transaction::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/transactions/'.$other->id)->assertStatus(404);
    }
}
