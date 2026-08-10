<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Family;
use App\Models\RecurringRule;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class RecurringRuleTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_expense_requires_wallet_id(): void
    {
        $this->actingAsFamilyMember('member');

        $this->postJson('/api/v1/recurring-rules', [
            'type' => 'expense',
            'amount' => 100_000,
            'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=1',
            'next_run_on' => now()->addMonth()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wallet_id']);
    }

    public function test_store_creates_recurring_expense(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        $wallet = Wallet::factory()->for($family)->create();

        $this->postJson('/api/v1/recurring-rules', [
            'type' => 'expense',
            'amount' => 150_000,
            'wallet_id' => $wallet->id,
            'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=1',
            'next_run_on' => now()->addMonth()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_changing_type_clears_stale_foreign_keys(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        $wallet = Wallet::factory()->for($family)->create();
        $source = \App\Models\IncomeSource::factory()->for($family)->create();
        $rule = RecurringRule::factory()->for($family)->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'source_id' => null]);

        $response = $this->putJson('/api/v1/recurring-rules/'.$rule->id, [
            'type' => 'income',
            'source_id' => $source->id,
        ])->assertOk();

        $response->assertJsonPath('data.wallet_id', null);
        $response->assertJsonPath('data.source_id', $source->id);
    }

    public function test_tenant_leak_cannot_view_other_familys_rule(): void
    {
        $this->actingAsFamilyMember('admin');
        $other = RecurringRule::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/recurring-rules/'.$other->id)->assertStatus(404);
    }
}
