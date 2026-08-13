<?php

use App\Models\ChatThread;
use App\Models\Family;
use App\Models\OnboardingAnswer;

test('question key must be unique per family', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    OnboardingAnswer::factory()->for($family)->create(['question_key' => 'members']);

    $this->postJson('/api/v1/onboarding-answers', ['question_key' => 'members'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['question_key']);
});

test('store and update', function () {
    $this->actingAsFamilyMember('member');

    $response = $this->postJson('/api/v1/onboarding-answers', [
        'question_key' => 'goals',
        'answer' => ['note' => 'Dana darurat'],
    ])->assertCreated();

    $id = $response->json('data.id');

    $this->putJson("/api/v1/onboarding-answers/{$id}", ['skipped' => true, 'answer' => null])
        ->assertOk()
        ->assertJsonPath('data.skipped', true);
});

test('tenant leak cannot view other familys answer', function () {
    $this->actingAsFamilyMember('admin');
    $other = OnboardingAnswer::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/onboarding-answers/'.$other->id)->assertStatus(404);
});

test('answering a question posts the next question into the onboarding thread', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create(['kind' => 'onboarding']);

    $this->postJson('/api/v1/onboarding-answers', [
        'question_key' => 'members',
        'answer' => ['note' => 'Aku & istri'],
    ])->assertCreated();

    $questions = config('amina.onboarding_questions');
    $nextKey = array_keys($questions)[1];

    $this->assertDatabaseHas('chat_messages', [
        'thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => $questions[$nextKey],
    ]);
});

test('skipping a question still advances to the next one', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create(['kind' => 'onboarding']);

    $this->postJson('/api/v1/onboarding-answers', ['question_key' => 'members', 'skipped' => true])
        ->assertCreated();

    $questions = config('amina.onboarding_questions');
    $nextKey = array_keys($questions)[1];

    $this->assertDatabaseHas('chat_messages', [
        'thread_id' => $thread->id,
        'content' => $questions[$nextKey],
    ]);
});

test('answering the last question marks family onboarding done and asks nothing further', function () {
    // onboarding_done defaults to a random boolean in FamilyFactory (used by
    // other tests that don't care about it) -- pin it false here since this
    // test asserts the transition from false to true.
    $family = Family::factory()->create(['onboarding_done' => false]);
    [, , $member] = $this->actingAsFamilyMember('member', $family);
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create(['kind' => 'onboarding']);

    $keys = array_keys(config('amina.onboarding_questions'));
    foreach (array_slice($keys, 0, -1) as $key) {
        OnboardingAnswer::factory()->for($family)->create(['question_key' => $key]);
    }

    expect($family->fresh()->onboarding_done)->toBeFalse();

    $this->postJson('/api/v1/onboarding-answers', ['question_key' => end($keys)])
        ->assertCreated();

    expect($family->fresh()->onboarding_done)->toBeTrue();
    expect($thread->messages()->count())->toBe(0);
});
