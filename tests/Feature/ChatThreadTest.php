<?php

use App\Models\ChatThread;
use App\Models\Family;

test('store sets member id from current member not body', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');

    $response = $this->postJson('/api/v1/chat-threads', [
        'title' => 'Tanya Amina',
        'member_id' => 'not-a-real-id',
    ])->assertCreated();

    $response->assertJsonPath('data.member_id', $member->id);
    $response->assertJsonPath('data.kind', 'general');
});

test('tenant leak cannot view other familys thread', function () {
    $this->actingAsFamilyMember('admin');
    $other = ChatThread::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/chat-threads/'.$other->id)->assertStatus(404);
});
