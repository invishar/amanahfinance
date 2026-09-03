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

test('onboarding masih berjalan selama families.onboarding_done false', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $family->update(['onboarding_done' => false]);
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create(['kind' => 'onboarding']);

    $this->getJson("/api/v1/chat-threads/{$thread->id}")
        ->assertOk()
        ->assertJsonPath('data.onboarding.done', false);
});

// Wawancara awal tidak lagi wizard berlangkah tetap: jumlah giliran ditentukan
// percakapan, dan yang menyalakan penanda selesai adalah tool
// finish_onboarding (lihat AssistantService::buildTools), bukan hitungan
// jawaban. Karena itu step/total/question_key sudah tidak ada di resource.
test('onboarding selesai begitu families.onboarding_done menyala', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $family->update(['onboarding_done' => true]);
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create(['kind' => 'onboarding']);

    $this->getJson("/api/v1/chat-threads/{$thread->id}")
        ->assertOk()
        ->assertJsonPath('data.onboarding.done', true);
});

test('tenant leak cannot view other familys thread', function () {
    $this->actingAsFamilyMember('admin');
    $other = ChatThread::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/chat-threads/'.$other->id)->assertStatus(404);
});
