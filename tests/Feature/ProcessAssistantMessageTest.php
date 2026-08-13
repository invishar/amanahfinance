<?php

use App\Jobs\ProcessAssistantMessage;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Support\Str;

test('failed() writes a system message once retries are exhausted', function () {
    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);

    (new ProcessAssistantMessage($userMessage->id))->failed(new RuntimeException('LLM timed out'));

    $this->assertDatabaseHas('chat_messages', [
        'thread_id' => $thread->id,
        'role' => 'system',
    ]);
});

test('failed() is a no-op for a message that no longer exists', function () {
    (new ProcessAssistantMessage((string) Str::uuid()))->failed(new RuntimeException('LLM timed out'));

    expect(ChatMessage::query()->where('role', 'system')->exists())->toBeFalse();
});
