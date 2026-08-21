<?php

use App\Models\ChatThread;
use App\Models\Family;
use App\Models\OnboardingAnswer;

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

test('onboarding progress exposes the next unanswered question key', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create(['kind' => 'onboarding']);
    OnboardingAnswer::factory()->for($family)->create(['question_key' => 'members']);

    $keys = array_keys(config('amina.onboarding_questions'));

    $this->getJson("/api/v1/chat-threads/{$thread->id}")
        ->assertOk()
        ->assertJsonPath('data.onboarding.question_key', $keys[1])
        ->assertJsonPath('data.onboarding.done', false);
});

test('onboarding progress question key is null once all questions are answered', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create(['kind' => 'onboarding']);
    foreach (array_keys(config('amina.onboarding_questions')) as $key) {
        OnboardingAnswer::factory()->for($family)->create(['question_key' => $key]);
    }

    $this->getJson("/api/v1/chat-threads/{$thread->id}")
        ->assertOk()
        ->assertJsonPath('data.onboarding.question_key', null)
        ->assertJsonPath('data.onboarding.done', true);
});

test('tenant leak cannot view other familys thread', function () {
    $this->actingAsFamilyMember('admin');
    $other = ChatThread::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/chat-threads/'.$other->id)->assertStatus(404);
});
