<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Wallet;
use App\Models\WalletBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class WalletBudgetTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_period_is_normalized_to_first_of_month(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        $wallet = Wallet::factory()->for($family)->create();

        $this->postJson("/api/v1/wallets/{$wallet->id}/budgets", [
            'period' => '2026-03-17',
            'amount' => 1_000_000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.period', '2026-03-01');
    }

    public function test_cannot_create_budget_for_another_familys_wallet(): void
    {
        $this->actingAsFamilyMember('admin');
        $otherWallet = Wallet::factory()->for(Family::factory())->create();

        $this->postJson("/api/v1/wallets/{$otherWallet->id}/budgets", [
            'period' => '2026-03-01',
            'amount' => 1_000_000,
        ])->assertStatus(404);
    }

    public function test_tenant_leak_on_shallow_show_route(): void
    {
        $this->actingAsFamilyMember('admin');
        $otherWallet = Wallet::factory()->for(Family::factory())->create();
        $otherBudget = WalletBudget::factory()->for($otherWallet)->create();

        $this->getJson('/api/v1/budgets/'.$otherBudget->id)->assertStatus(403);
    }
}
