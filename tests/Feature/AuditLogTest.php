<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Family;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_index_lists_only_current_familys_logs(): void
    {
        [, $family] = $this->actingAsFamilyMember('member');
        AuditLog::factory()->for($family)->create();
        AuditLog::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/audit-logs')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_no_write_routes_exist(): void
    {
        $this->actingAsFamilyMember('admin');

        // 405, not 404: the collection URI exists (GET index), POST just isn't routed to it.
        $this->postJson('/api/v1/audit-logs', ['entity' => 'transaction'])->assertStatus(405);
    }

    public function test_tenant_leak_cannot_view_other_familys_log(): void
    {
        $this->actingAsFamilyMember('admin');
        $other = AuditLog::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/audit-logs/'.$other->id)->assertStatus(404);
    }
}
