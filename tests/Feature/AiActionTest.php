<?php

use App\Models\AiAction;
use App\Models\Family;

test('index lists only current familys actions', function () {
    [, $family] = $this->actingAsFamilyMember('member');
    AiAction::factory()->for($family)->create();
    AiAction::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/ai-actions')->assertOk()->assertJsonCount(1, 'data');
});

test('no write routes exist', function () {
    $this->actingAsFamilyMember('admin');

    // 405, not 404: the collection URI exists (GET index), POST just isn't routed to it.
    $this->postJson('/api/v1/ai-actions', ['action' => 'advice'])->assertStatus(405);
});

test('tenant leak cannot view other familys action', function () {
    $this->actingAsFamilyMember('admin');
    $other = AiAction::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/ai-actions/'.$other->id)->assertStatus(404);
});
