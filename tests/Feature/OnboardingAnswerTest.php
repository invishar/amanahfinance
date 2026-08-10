<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\OnboardingAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class OnboardingAnswerTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_question_key_must_be_unique_per_family(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        OnboardingAnswer::factory()->for($family)->create(['question_key' => 'members']);

        $this->postJson('/api/v1/onboarding-answers', ['question_key' => 'members'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['question_key']);
    }

    public function test_store_and_update(): void
    {
        $this->actingAsFamilyMember('member');

        $response = $this->postJson('/api/v1/onboarding-answers', [
            'question_key' => 'goals',
            'answer' => ['note' => 'Dana darurat'],
        ])->assertCreated();

        $id = $response->json('data.id');

        $this->putJson("/api/v1/onboarding-answers/{$id}", ['skipped' => true, 'answer' => null])
            ->assertOk()
            ->assertJsonPath('data.skipped', true);
    }

    public function test_tenant_leak_cannot_view_other_familys_answer(): void
    {
        $this->actingAsFamilyMember('admin');
        $other = OnboardingAnswer::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/onboarding-answers/'.$other->id)->assertStatus(404);
    }
}
