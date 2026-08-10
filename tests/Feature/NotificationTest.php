<?php

use App\Models\Family;
use App\Models\Notification;

test('only admin can create notification', function () {
    $this->actingAsFamilyMember('member');

    $this->postJson('/api/v1/notifications', [
        'kind' => 'bill_due',
        'title' => 'Tagihan listrik',
    ])->assertStatus(403);
});

test('member can mark notification read', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    $notification = Notification::factory()->for($family)->create(['read_at' => null]);

    $this->putJson('/api/v1/notifications/'.$notification->id, ['read_at' => now()->toIso8601String()])
        ->assertOk();

    $this->assertNotNull($notification->fresh()->read_at);
});

test('tenant leak cannot view other familys notification', function () {
    $this->actingAsFamilyMember('admin');
    $other = Notification::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/notifications/'.$other->id)->assertStatus(404);
});
