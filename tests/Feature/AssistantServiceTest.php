<?php

use App\Models\Account;
use App\Models\AiAction;
use App\Models\AiLog;
use App\Models\AiProviderError;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\IncomeSource;
use App\Models\SavingsGoal;
use App\Models\Wallet;
use App\Services\Ai\AssistantService;
use App\Services\Ai\Contracts\ConversationRunner;
use App\Services\Ai\ConversationResult;
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

        public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): ConversationResult
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
        public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): ConversationResult
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

test('a successful respond writes an ai_logs row only when APP_ENV=local', function () {
    app()['env'] = 'local';

    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user', 'content' => 'halo amina']);

    bindConversationRunner(finalText: 'Halo juga!');

    app(AssistantService::class)->respond($userMessage);

    $log = AiLog::query()->where('message_id', $userMessage->id)->first();
    expect($log)->not->toBeNull();
    expect($log->family_id)->toBe($family->id);
    expect($log->thread_id)->toBe($thread->id);
    expect($log->user_prompt)->toBe('halo amina');
    expect($log->system_prompt)->not->toBeEmpty();
    expect($log->input_tokens)->toBe(123);
    expect($log->output_tokens)->toBe(45);
});

test('a successful respond does not write an ai_logs row outside APP_ENV=local', function () {
    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);

    bindConversationRunner(finalText: 'Halo juga!');

    app(AssistantService::class)->respond($userMessage);

    expect(AiLog::query()->where('message_id', $userMessage->id)->exists())->toBeFalse();
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

// Runner yang cuma menangkap system prompt, supaya isi konteks bisa diperiksa
// tanpa menyentuh jaringan. Dipakai dua test di bawah.
function captureSystemPrompt(ChatMessage $userMessage): string
{
    $holder = new stdClass;
    $holder->system = '';

    app()->bind(ConversationRunner::class, fn () => new class($holder) implements ConversationRunner
    {
        public function __construct(private stdClass $holder) {}

        public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): ConversationResult
        {
            $this->holder->system = $system;

            return new ConversationResult('oke', 1, 1);
        }
    });

    app(AssistantService::class)->respond($userMessage);

    return $holder->system;
}

test('system prompt memuat katalog nama akun, wallet, sumber, dan target', function () {
    $family = Family::factory()->create(['name' => 'Keluarga Uji']);
    $member = FamilyMember::factory()->for($family)->create();
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);

    $account = Account::factory()->for($family)->create(['name' => 'Bank Jago']);
    $wallet = Wallet::factory()->for($family)->create(['name' => 'Kopi Harian']);
    $source = IncomeSource::factory()->for($family)->create(['name' => 'Royalti Buku']);
    $goal = SavingsGoal::factory()->for($family)->create([
        'target_name' => 'Umrah Ibu', 'status' => 'active',
    ]);

    $system = captureSystemPrompt($userMessage);

    // create_transaction mewajibkan account_name di SEMUA jenis transaksi, dan
    // goal_name untuk savings -- kalau nama-nama ini tidak ikut dikirim, model
    // diminta menyebut sesuatu yang tak pernah ia lihat.
    expect($system)->toContain($account->name);
    expect($system)->toContain($wallet->name);
    expect($system)->toContain($source->name);
    expect($system)->toContain($goal->target_name);
    expect($system)->toContain(now()->toDateString());
});

test('system prompt tidak lagi menyertakan detail per-wallet yang besar', function () {
    $family = Family::factory()->create();
    $member = FamilyMember::factory()->for($family)->create();
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();
    $userMessage = ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user']);
    Wallet::factory()->for($family)->create(['name' => 'Kopi Harian']);

    $system = captureSystemPrompt($userMessage);

    // Rincian budget/spent/status per wallet hanya boleh datang lewat tool
    // get_financial_summary, bukan menempel di setiap pesan.
    expect($system)->not->toContain('"remaining"');
    expect($system)->not->toContain('"percent"');
    expect($system)->not->toContain('"wallet_id"');
    // ...tapi angka kas ringkas tetap inline supaya pertanyaan umum tidak
    // memicu satu putaran tool call tambahan.
    expect($system)->toContain('kas_bulan_ini');
});
