<?php

use App\Models\Account;
use App\Models\AiAction;
use App\Models\AiProviderError;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Wallet;
use App\Services\Ai\AssistantService;
use App\Services\Ai\Contracts\ConversationRunner;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
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

test('a non-2xx provider response is logged to the dedicated ai channel', function () {
    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);

    $response = new Response(new Psr7Response(429, [], 'rate limited'));
    app()->bind(ConversationRunner::class, fn () => new class($response) implements ConversationRunner
    {
        public function __construct(private Response $response) {}

        public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): string
        {
            throw new RequestException($this->response);
        }
    });

    Log::shouldReceive('channel')->once()->with('ai')->andReturnSelf();
    Log::shouldReceive('warning')->once()->with('Panggilan LLM gagal', Mockery::on(function (array $context) use ($family, $userMessage) {
        return $context['status'] === 429
            && $context['family_id'] === $family->id
            && $context['message_id'] === $userMessage->id;
    }));

    expect(fn () => app(AssistantService::class)->respond($userMessage))
        ->toThrow(RequestException::class);

    $error = AiProviderError::query()->where('message_id', $userMessage->id)->first();
    expect($error)->not->toBeNull();
    expect($error->status)->toBe(429);
    expect($error->family_id)->toBe($family->id);
    expect($error->exception)->toBe(RequestException::class);
});

test('a non-HTTP provider failure is still logged with a null status', function () {
    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);

    app()->bind(ConversationRunner::class, fn () => new class implements ConversationRunner
    {
        public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): string
        {
            throw new RuntimeException('connection timed out');
        }
    });

    Log::shouldReceive('channel')->once()->with('ai')->andReturnSelf();
    Log::shouldReceive('warning')->once()->with('Panggilan LLM gagal', Mockery::on(fn (array $context) => $context['status'] === null));

    expect(fn () => app(AssistantService::class)->respond($userMessage))
        ->toThrow(RuntimeException::class);

    $error = AiProviderError::query()->where('message_id', $userMessage->id)->first();
    expect($error)->not->toBeNull();
    expect($error->status)->toBeNull();
});

test('fail writes a system message and bumps last_message_at', function () {
    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create(['last_message_at' => null]);
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user', 'content' => 'abis jajan 20rb']);

    $errorMessage = app(AssistantService::class)->fail($userMessage);

    expect($errorMessage->role)->toBe('system');
    expect($errorMessage->thread_id)->toBe($thread->id);
    expect($thread->fresh()->last_message_at)->not->toBeNull();
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
