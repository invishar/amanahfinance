<?php

namespace Tests\Feature;

use App\Models\AiAction;
use App\Models\Family;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class AiActionTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_index_lists_only_current_familys_actions(): void
    {
        [, $family] = $this->actingAsFamilyMember('member');
        AiAction::factory()->for($family)->create();
        AiAction::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/ai-actions')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_no_write_routes_exist(): void
    {
        $this->actingAsFamilyMember('admin');

        // 405, not 404: the collection URI exists (GET index), POST just isn't routed to it.
        $this->postJson('/api/v1/ai-actions', ['action' => 'advice'])->assertStatus(405);
    }

    public function test_tenant_leak_cannot_view_other_familys_action(): void
    {
        $this->actingAsFamilyMember('admin');
        $other = AiAction::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/ai-actions/'.$other->id)->assertStatus(404);
    }
}
