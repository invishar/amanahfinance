<?php

use App\Models\AiAction;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Services\Ai\ChatProgress;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    // Real duration/poll config would make every test wait ~20s of real
    // wall-clock time; 0/0 forces exactly one pass through the loop.
    config(['amina.sse.duration_seconds' => 0, 'amina.sse.poll_interval_ms' => 0]);
});

test('stream emits new assistant messages and pending action cards after the cursor', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);
    ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'assistant', 'content' => 'Halo dari Amina!']);
    $aiAction = AiAction::factory()->for($family)->for($userMessage, 'message')->create(['action' => 'advice', 'status' => 'pending']);

    $cursor = urlencode(now()->subMinute()->toIso8601String());
    $response = $this->get("/api/v1/chat-threads/{$thread->id}/stream?after={$cursor}");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

    $content = $response->streamedContent();

    expect($content)->toContain('event: message');
    expect($content)->toContain('Halo dari Amina!');
    expect($content)->toContain('event: action_card');
    expect($content)->toContain($aiAction->id);
    expect($content)->toContain('event: retry');
});

test('stream excludes events at or before the cursor', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $old = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'assistant', 'content' => 'Pesan basi']);
    ChatMessage::query()->whereKey($old->id)->update(['created_at' => now()->subHour()]);

    $cursor = urlencode(now()->subMinutes(30)->toIso8601String());
    $response = $this->get("/api/v1/chat-threads/{$thread->id}/stream?after={$cursor}");

    expect($response->streamedContent())->not->toContain('Pesan basi');
});

test('confirmed and rejected actions never appear as action cards', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);
    AiAction::factory()->for($family)->for($userMessage, 'message')->create(['action' => 'advice', 'status' => 'confirmed']);
    AiAction::factory()->for($family)->for($userMessage, 'message')->create(['action' => 'advice', 'status' => 'rejected']);

    $cursor = urlencode(now()->subMinute()->toIso8601String());
    $response = $this->get("/api/v1/chat-threads/{$thread->id}/stream?after={$cursor}");

    expect($response->streamedContent())->not->toContain('event: action_card');
});

test('stream emits thinking when the newest message is an unanswered user message', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);

    $response = $this->get("/api/v1/chat-threads/{$thread->id}/stream");

    $content = $response->streamedContent();
    expect($content)->toContain('event: thinking');
    expect($content)->toContain($userMessage->id);
});

test('stream does not emit thinking once the newest message is already answered', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);
    ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'assistant', 'content' => 'Beres!']);

    $response = $this->get("/api/v1/chat-threads/{$thread->id}/stream");

    expect($response->streamedContent())->not->toContain('event: thinking');
});

test('stream surfaces role=system messages as error events', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $errorMessage = ChatMessage::factory()->for($thread, 'thread')->create([
        'role' => 'system',
        'content' => 'Amina lagi ada gangguan teknis. Coba kirim pesan itu lagi beberapa saat lagi ya.',
    ]);

    $cursor = urlencode(now()->subMinute()->toIso8601String());
    $response = $this->get("/api/v1/chat-threads/{$thread->id}/stream?after={$cursor}");

    $content = $response->streamedContent();
    expect($content)->toContain('event: error');
    expect($content)->toContain($errorMessage->id);
    expect($content)->not->toContain('event: message');
});

test('cannot stream another familys thread', function () {
    $this->actingAsFamilyMember('admin');
    $otherFamily = Family::factory()->create();
    $otherMember = FamilyMember::factory()->for($otherFamily)->create();
    $otherThread = ChatThread::factory()->for($otherFamily)->for($otherMember, 'member')->create();

    $this->get("/api/v1/chat-threads/{$otherThread->id}/stream")->assertStatus(404);
});

// --- Worker inline: stream yang membangunkan antrean, bukan cron ----------
//
// Tanpa ini, balasan Amina baru dikerjakan saat `schedule:run` berikutnya
// menyala -- di staging jaraknya sempat 8 menit, padahal panggilan LLM-nya
// 1-3 detik. Test di sini memaksa driver non-sync supaya jalur itu benar-benar
// dievaluasi (phpunit.xml memakai `sync`, yang sengaja dilewati).

test('stream menjalankan worker antrean saat ada pesan user yang belum dijawab', function () {
    config(['queue.default' => 'database']);
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);

    Artisan::shouldReceive('call')
        ->once()
        ->with('queue:work', Mockery::on(fn ($options) => $options['--once'] === true && $options['--sleep'] === 0));

    $this->get("/api/v1/chat-threads/{$thread->id}/stream")->streamedContent();
});

test('stream tidak menjalankan worker kalau tidak ada yang menunggu balasan', function () {
    config(['queue.default' => 'database']);
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    // Pesan terakhir sudah dari Amina -- tidak ada giliran yang menggantung.
    ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'assistant']);

    Artisan::shouldReceive('call')->never();

    $this->get("/api/v1/chat-threads/{$thread->id}/stream")->streamedContent();
});

test('worker inline bisa dimatikan lewat config', function () {
    config(['queue.default' => 'database', 'amina.sse.inline_worker.enabled' => false]);
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);

    Artisan::shouldReceive('call')->never();

    $this->get("/api/v1/chat-threads/{$thread->id}/stream")->streamedContent();
});

test('worker gagal tidak mematikan stream', function () {
    config(['queue.default' => 'database']);
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);

    Artisan::shouldReceive('call')
        ->once()
        ->andThrow(new RuntimeException('worker meledak'));

    $response = $this->get("/api/v1/chat-threads/{$thread->id}/stream");

    // Stream harus tetap utuh sampai event penutup -- kalau tidak, klien
    // kehilangan kursor `after` dan reconnect-nya kacau.
    expect($response->streamedContent())->toContain('event: retry');
});

test('turn stream recovers same-second reply and excludes older cards', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $old = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);
    $oldCard = AiAction::factory()->for($family)->for($old, 'message')->create(['status' => 'pending']);
    ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'assistant', 'content' => 'Jawaban lama']);
    $turn = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);
    $card = AiAction::factory()->for($family)->for($turn, 'message')->create(['status' => 'pending']);
    ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'assistant', 'content' => 'Jawaban terbaru']);
    ChatProgress::report($turn, 'drafting');

    $content = $this->get("/api/v1/chat-threads/{$thread->id}/stream?message_id={$turn->id}")->streamedContent();
    expect($content)->toContain('Jawaban terbaru')->toContain($card->id)
        ->toContain('event: progress')->toContain('drafting')->toContain('"message_id":"'.$turn->id.'"')
        ->not->toContain('Jawaban lama')->not->toContain($oldCard->id);
});

test('turn stream rejects a message from another thread and malformed cursor', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $other = ChatMessage::factory()->create(['role' => 'user']);
    $this->getJson("/api/v1/chat-threads/{$thread->id}/stream?message_id={$other->id}")->assertNotFound();
    $this->getJson("/api/v1/chat-threads/{$thread->id}/stream?after=broken")->assertUnprocessable();
});

test('inline worker progress is relayed before its final result', function () {
    config(['queue.default' => 'database']);
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $turn = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);
    Artisan::shouldReceive('call')->once()->andReturnUsing(function () use ($turn, $thread) {
        ChatProgress::report($turn, 'drafting');
        ChatProgress::report($turn, 'composing');
        ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'assistant', 'content' => 'Selesai']);

        return 0;
    });
    $content = $this->get("/api/v1/chat-threads/{$thread->id}/stream?message_id={$turn->id}")->streamedContent();
    expect(strpos($content, 'drafting'))->toBeLessThan(strpos($content, 'composing'));
    expect(strpos($content, 'composing'))->toBeLessThan(strpos($content, 'event: message'));
});
