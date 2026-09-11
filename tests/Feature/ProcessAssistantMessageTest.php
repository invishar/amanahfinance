<?php

use App\Jobs\ProcessAssistantMessage;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Services\Ai\AssistantService;
use App\Support\CurrentFamily;
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

test('inline job uses its own family and restores the viewing request family', function () {
    [, $viewerFamily, $viewerMember] = $this->actingAsFamilyMember('member');
    app(CurrentFamily::class)->set($viewerFamily, $viewerMember);
    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $message = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);
    $requestContext = app(CurrentFamily::class);
    $assistant = Mockery::mock(AssistantService::class);
    $assistant->shouldReceive('respond')->once()->andReturnUsing(function ($received) use ($family, $message) {
        expect(app(CurrentFamily::class)->id())->toBe($family->id);
        expect($received->id)->toBe($message->id);
        throw new RuntimeException('retry provider');
    });
    expect(fn () => (new ProcessAssistantMessage($message->id))->handle($assistant))->toThrow(RuntimeException::class);
    expect(app(CurrentFamily::class))->toBe($requestContext);
    expect(app(CurrentFamily::class)->id())->toBe($viewerFamily->id);
});
