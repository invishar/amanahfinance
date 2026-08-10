<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Family;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_store_sets_current_balance_to_opening_balance(): void
    {
        $this->actingAsFamilyMember('member');

        $response = $this->postJson('/api/v1/accounts', [
            'name' => 'BCA',
            'account_type' => 'bank',
            'opening_balance' => 500_000,
        ])->assertCreated();

        $response->assertJsonPath('data.opening_balance', 500_000);
        $response->assertJsonPath('data.current_balance', 500_000);
    }

    public function test_viewer_cannot_create_account(): void
    {
        $this->actingAsFamilyMember('viewer');

        $this->postJson('/api/v1/accounts', ['name' => 'BCA', 'account_type' => 'bank'])
            ->assertStatus(403);
    }

    public function test_update_cannot_change_balances_directly(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        $account = Account::factory()->for($family)->create(['current_balance' => 100_000, 'opening_balance' => 100_000]);

        $this->putJson('/api/v1/accounts/'.$account->id, [
            'name' => 'BCA Utama',
            'current_balance' => 999_999_999,
        ])->assertOk();

        $account->refresh();
        $this->assertSame('BCA Utama', $account->name);
        $this->assertSame(100_000, $account->current_balance);
    }

    public function test_delete_blocked_with_409_when_referenced_by_transaction(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        $account = Account::factory()->for($family)->create();
        Transaction::factory()->for($family)->create(['account_id' => $account->id, 'wallet_id' => null, 'type' => 'income', 'source_id' => \App\Models\IncomeSource::factory()->for($family)]);

        $this->deleteJson('/api/v1/accounts/'.$account->id)->assertStatus(409);
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    public function test_tenant_leak_cannot_view_other_familys_account(): void
    {
        $this->actingAsFamilyMember('admin');
        $otherAccount = Account::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/accounts/'.$otherAccount->id)->assertStatus(404);
    }
}
