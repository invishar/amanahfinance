<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class SavingsGoalTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_current_amount_is_not_client_writable(): void
    {
        $this->actingAsFamilyMember('member');

        $response = $this->postJson('/api/v1/savings-goals', [
            'target_name' => 'Dana Darurat',
            'target_amount' => 10_000_000,
            'current_amount' => 9_999_999,
        ])->assertCreated();

        $response->assertJsonPath('data.current_amount', 0);
    }

    public function test_setting_status_to_achieved_stamps_achieved_at(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        $goal = SavingsGoal::factory()->for($family)->create(['status' => 'active', 'achieved_at' => null]);

        $response = $this->putJson('/api/v1/savings-goals/'.$goal->id, ['status' => 'achieved'])
            ->assertOk();

        $this->assertNotNull($response->json('data.achieved_at'));
    }

    public function test_delete_blocked_with_409_when_referenced_by_transaction(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        $goal = SavingsGoal::factory()->for($family)->create();
        Transaction::factory()->savings()->for($family)->create(['goal_id' => $goal->id]);

        $this->deleteJson('/api/v1/savings-goals/'.$goal->id)->assertStatus(409);
    }

    public function test_tenant_leak_cannot_view_other_familys_goal(): void
    {
        $this->actingAsFamilyMember('admin');
        $other = SavingsGoal::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/savings-goals/'.$other->id)->assertStatus(404);
    }
}
