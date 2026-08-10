<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_only_admin_can_create_notification(): void
    {
        $this->actingAsFamilyMember('member');

        $this->postJson('/api/v1/notifications', [
            'kind' => 'bill_due',
            'title' => 'Tagihan listrik',
        ])->assertStatus(403);
    }

    public function test_member_can_mark_notification_read(): void
    {
        [, $family] = $this->actingAsFamilyMember('member');
        $notification = Notification::factory()->for($family)->create(['read_at' => null]);

        $this->putJson('/api/v1/notifications/'.$notification->id, ['read_at' => now()->toIso8601String()])
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_tenant_leak_cannot_view_other_familys_notification(): void
    {
        $this->actingAsFamilyMember('admin');
        $other = Notification::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/notifications/'.$other->id)->assertStatus(404);
    }
}
