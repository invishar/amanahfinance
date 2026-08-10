<?php

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
