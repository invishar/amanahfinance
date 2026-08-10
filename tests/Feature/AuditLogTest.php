<?php

use App\Models\AuditLog;
use App\Models\Family;

test('index lists only current familys logs', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    AuditLog::factory()->for($family)->create();
    AuditLog::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/audit-logs')->assertOk()->assertJsonCount(1, 'data');
});

test('no write routes exist', function () {
    $this->actingAsFamilyMember('admin');

    // 405, not 404: the collection URI exists (GET index), POST just isn't routed to it.
    $this->postJson('/api/v1/audit-logs', ['entity' => 'transaction'])->assertStatus(405);
});

test('tenant leak cannot view other familys log', function () {
    $this->actingAsFamilyMember('admin');
    $other = AuditLog::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/audit-logs/'.$other->id)->assertStatus(404);
});
