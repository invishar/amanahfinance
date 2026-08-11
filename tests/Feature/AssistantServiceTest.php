<?php

use App\Models\Account;
use App\Models\AiAction;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Wallet;
use App\Services\Ai\AssistantService;
use App\Services\Ai\Contracts\ConversationRunner;
use Tests\Support\FakeConversationRunner;

// LLM di-mock lewat ConversationRunner (CLAUDE.md: "LLM selalu di-mock di
// test") -- fake ini menjalankan closure BetaRunnableTool yang sesungguhnya,
// jadi resolusi nama, penyimpanan ai_actions.pending, dsb benar-benar diuji.
function bindConversationRunner(array $toolCalls = [], string $finalText = 'Oke, siap!'): void
{
    app()->bind(ConversationRunner::class, fn () => new FakeConversationRunner($toolCalls, $finalText));
}

test('create_transaction tool call stages a pending ai_action with resolved ids', function () {
    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    $account = Account::factory()->for($family)->create(['name' => 'GoPay']);
    $wallet = Wallet::factory()->for($family)->create(['name' => 'Jajan']);
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create([
        'role' => 'user',
        'content' => 'abis jajan 20rb di gopay',
    ]);

    bindConversationRunner(
        toolCalls: [[
            'tool' => 'create_transaction',
            'input' => [
                'type' => 'expense',
                'amount' => 20000,
                'account_name' => 'gopay',
                'wallet_name' => 'Jajan',
                'note' => 'jajan',
            ],
        ]],
        finalText: 'Oke, sudah aku siapin draft pengeluarannya, tinggal dikonfirmasi ya!',
    );

    $reply = app(AssistantService::class)->respond($userMessage);

    expect($reply->role)->toBe('assistant');
    expect($reply->thread_id)->toBe($thread->id);
    expect($reply->content)->toBe('Oke, sudah aku siapin draft pengeluarannya, tinggal dikonfirmasi ya!');

    $aiAction = AiAction::query()->where('message_id', $userMessage->id)->first();
    expect($aiAction)->not->toBeNull();
    expect($aiAction->action)->toBe('create_transaction');
    expect($aiAction->status)->toBe('pending');
    expect($aiAction->payload)->toMatchArray([
        'type' => 'expense',
        'amount' => 20000,
        'account_id' => $account->id,
        'wallet_id' => $wallet->id,
    ]);
});

test('unresolvable account name is left blank instead of guessed', function () {
    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    Account::factory()->for($family)->create(['name' => 'BCA']);
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);

    bindConversationRunner(toolCalls: [[
        'tool' => 'create_transaction',
        'input' => [
            'type' => 'expense',
            'amount' => 15000,
            'account_name' => 'rekening antah berantah',
            'wallet_name' => 'wallet ga jelas',
        ],
    ]]);

    app(AssistantService::class)->respond($userMessage);

    $aiAction = AiAction::query()->where('message_id', $userMessage->id)->first();
    expect($aiAction->payload['account_id'])->toBeNull();
    expect($aiAction->payload['wallet_id'])->toBeNull();
});

test('respond with no tool calls just persists the assistant reply', function () {
    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user', 'content' => 'halo']);

    bindConversationRunner(finalText: 'Halo juga! Ada yang bisa aku bantu?');

    $reply = app(AssistantService::class)->respond($userMessage);

    expect($reply->content)->toBe('Halo juga! Ada yang bisa aku bantu?');
    expect(AiAction::query()->where('message_id', $userMessage->id)->exists())->toBeFalse();
});
