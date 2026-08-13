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
    $response->assertJsonPath('data.onboarding', null);
});

test('index filters by kind', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $onboarding = ChatThread::factory()->for($family)->for($member, 'member')->create(['kind' => 'onboarding']);
    ChatThread::factory()->for($family)->for($member, 'member')->create(['kind' => 'general']);

    $ids = $this->getJson('/api/v1/chat-threads?kind=onboarding')->assertOk()->json('data.*.id');

    expect($ids)->toBe([$onboarding->id]);
});

test('index rejects an invalid kind filter', function () {
    $this->actingAsFamilyMember('member');

    $this->getJson('/api/v1/chat-threads?kind=whatever')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['kind']);
});

test('tenant leak cannot view other familys thread', function () {
    $this->actingAsFamilyMember('admin');
    $other = ChatThread::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/chat-threads/'.$other->id)->assertStatus(404);
});
