<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;

test('latest messages includes the newest reply beyond the first fifty messages in stable order', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $messages = ChatMessage::factory()->count(55)->for($thread, 'thread')->create(['role' => 'assistant']);

    $this->getJson("/api/v1/chat-threads/{$thread->id}/messages?latest=1")
        ->assertOk()->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.id', $messages[5]->id)
        ->assertJsonPath('data.49.id', $messages[54]->id);
});

test('latest messages cannot expose another family', function () {
    $this->actingAsFamilyMember('member');
    $other = ChatThread::factory()->create();
    $this->getJson("/api/v1/chat-threads/{$other->id}/messages?latest=1")->assertNotFound();
});

test('store forces role to user regardless of input', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();

    $response = $this->postJson("/api/v1/chat-threads/{$thread->id}/messages", [
        'content' => 'Halo Amina',
        'role' => 'assistant',
    ])->assertCreated();

    $response->assertJsonPath('data.role', 'user');
    $this->assertNotNull($thread->fresh()->last_message_at);
});

test('cannot post message into another familys thread', function () {
    $this->actingAsFamilyMember('member');
    $otherFamily = Family::factory()->create();
    $otherMember = FamilyMember::factory()->for($otherFamily)->create();
    $otherThread = ChatThread::factory()->for($otherFamily)->for($otherMember, 'member')->create();

    $this->postJson("/api/v1/chat-threads/{$otherThread->id}/messages", ['content' => 'hai'])
        ->assertStatus(404);
});

test('tenant leak on shallow show route', function () {
    $this->actingAsFamilyMember('admin');
    $otherFamily = Family::factory()->create();
    $otherMember = FamilyMember::factory()->for($otherFamily)->create();
    $otherThread = ChatThread::factory()->for($otherFamily)->for($otherMember, 'member')->create();
    $otherMessage = ChatMessage::factory()->for($otherThread, 'thread')->create();

    $this->getJson('/api/v1/messages/'.$otherMessage->id)->assertStatus(403);
});
