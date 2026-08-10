<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_member_can_create_wallet(): void
    {
        $this->actingAsFamilyMember('member');

        $this->postJson('/api/v1/wallets', ['name' => 'Makan'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Makan')
            ->assertJsonPath('data.monthly_budget', 0);
    }

    public function test_name_must_be_unique_within_family(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        Wallet::factory()->for($family)->create(['name' => 'Makan']);

        $this->postJson('/api/v1/wallets', ['name' => 'Makan'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_same_name_allowed_in_a_different_family(): void
    {
        Wallet::factory()->for(Family::factory())->create(['name' => 'Makan']);
        $this->actingAsFamilyMember('admin');

        $this->postJson('/api/v1/wallets', ['name' => 'Makan'])->assertCreated();
    }

    public function test_only_admin_can_delete(): void
    {
        [, $family] = $this->actingAsFamilyMember('member');
        $wallet = Wallet::factory()->for($family)->create();

        $this->deleteJson('/api/v1/wallets/'.$wallet->id)->assertStatus(403);
    }

    public function test_tenant_leak_cannot_view_other_familys_wallet(): void
    {
        $this->actingAsFamilyMember('admin');
        $otherWallet = Wallet::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/wallets/'.$otherWallet->id)->assertStatus(404);
    }
}
