<?php

use App\Models\Account;
use App\Models\AiAction;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\IncomeSource;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\Wallet;

function draftMessage(Family $family, FamilyMember $member, string $inputMode = 'text'): ChatMessage
{
    $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();

    return ChatMessage::factory()->for($thread, 'thread')->create(['role' => 'user', 'input_mode' => $inputMode]);
}

test('confirming create_transaction writes a real transaction and moves the balance', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create(['opening_balance' => 1_000_000, 'current_balance' => 1_000_000]);
    $wallet = Wallet::factory()->for($family)->create();
    $message = draftMessage($family, $member, 'text');

    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'create_transaction',
        'payload' => [
            'type' => 'expense',
            'amount' => 20000,
            'transaction_date' => now()->toDateString(),
            'note' => 'jajan',
            'account_id' => $account->id,
            'wallet_id' => $wallet->id,
            'source_id' => null,
            'to_account_id' => null,
            'goal_id' => null,
        ],
    ]);

    $response = $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm")->assertOk();

    $response->assertJsonPath('data.status', 'confirmed');
    $response->assertJsonPath('data.result_table', 'transactions');

    $transaction = Transaction::query()->findOrFail($response->json('data.result_id'));
    expect($transaction->origin)->toBe('chat_text');
    expect($transaction->amount)->toBe(20000);
    expect($account->fresh()->current_balance)->toBe(980_000);
});

test('confirming create_transaction from a voice message tags origin chat_voice', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();
    $wallet = Wallet::factory()->for($family)->create();
    $message = draftMessage($family, $member, 'voice');

    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'create_transaction',
        'payload' => [
            'type' => 'expense', 'amount' => 5000, 'transaction_date' => now()->toDateString(),
            'account_id' => $account->id, 'wallet_id' => $wallet->id,
            'source_id' => null, 'to_account_id' => null, 'goal_id' => null,
        ],
    ]);

    $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm")->assertOk();

    $transaction = Transaction::query()->where('family_id', $family->id)->firstOrFail();
    expect($transaction->origin)->toBe('chat_voice');
});

test('confirming with edits overrides the payload and marks the action edited', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create();
    $wallet = Wallet::factory()->for($family)->create();
    $message = draftMessage($family, $member);

    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'create_transaction',
        'payload' => [
            'type' => 'expense', 'amount' => 20000, 'transaction_date' => now()->toDateString(),
            'account_id' => $account->id, 'wallet_id' => $wallet->id,
            'source_id' => null, 'to_account_id' => null, 'goal_id' => null,
        ],
    ]);

    $response = $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm", ['amount' => 25000])->assertOk();

    $response->assertJsonPath('data.status', 'edited');
    $transaction = Transaction::query()->findOrFail($response->json('data.result_id'));
    expect($transaction->amount)->toBe(25000);
});

test('confirming with an unresolved account_id fails validation and leaves the draft pending', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $wallet = Wallet::factory()->for($family)->create();
    $message = draftMessage($family, $member);

    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'create_transaction',
        'payload' => [
            'type' => 'expense', 'amount' => 20000, 'transaction_date' => now()->toDateString(),
            'account_id' => null, 'wallet_id' => $wallet->id,
            'source_id' => null, 'to_account_id' => null, 'goal_id' => null,
        ],
    ]);

    $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm")->assertStatus(422);

    expect($aiAction->fresh()->status)->toBe('pending');
});

test('confirming with an edited account_id from another family is rejected', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $wallet = Wallet::factory()->for($family)->create();
    $foreignAccount = Account::factory()->for(Family::factory())->create();
    $message = draftMessage($family, $member);

    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'create_transaction',
        'payload' => [
            'type' => 'expense', 'amount' => 20000, 'transaction_date' => now()->toDateString(),
            'account_id' => null, 'wallet_id' => $wallet->id,
            'source_id' => null, 'to_account_id' => null, 'goal_id' => null,
        ],
    ]);

    $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm", ['account_id' => $foreignAccount->id])
        ->assertStatus(422);

    expect(Transaction::query()->count())->toBe(0);
});

test('confirming create_wallet writes a real wallet', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $message = draftMessage($family, $member);
    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'create_wallet',
        'payload' => ['name' => 'Hiburan', 'icon' => null, 'color' => null, 'monthly_budget' => 500000],
    ]);

    $response = $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm")->assertOk();

    $response->assertJsonPath('data.result_table', 'wallets');
    $wallet = Wallet::query()->findOrFail($response->json('data.result_id'));
    expect($wallet->name)->toBe('Hiburan');
    expect($wallet->family_id)->toBe($family->id);
});

test('confirming create_account writes a real account', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $message = draftMessage($family, $member);
    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'create_account',
        'payload' => ['name' => 'Jago', 'account_type' => 'bank', 'institution' => 'Bank Jago', 'opening_balance' => 100000],
    ]);

    $response = $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm")->assertOk();

    $account = Account::query()->findOrFail($response->json('data.result_id'));
    expect($account->current_balance)->toBe(100000);
});

test('confirming create_income_source writes a real income source', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $message = draftMessage($family, $member);
    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'create_income_source',
        'payload' => ['name' => 'Freelance', 'expected_amount' => 3000000, 'cadence' => 'monthly'],
    ]);

    $response = $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm")->assertOk();

    expect(IncomeSource::query()->findOrFail($response->json('data.result_id'))->name)->toBe('Freelance');
});

test('confirming create_savings_goal writes a real savings goal', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $message = draftMessage($family, $member);
    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'create_savings_goal',
        'payload' => ['target_name' => 'DP Rumah', 'target_amount' => 50000000, 'deadline' => null, 'account_id' => null],
    ]);

    $response = $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm")->assertOk();

    expect(SavingsGoal::query()->findOrFail($response->json('data.result_id'))->target_amount)->toBe(50000000);
});

test('confirming advice acknowledges the card without writing any business row', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $message = draftMessage($family, $member);
    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'advice',
        'payload' => ['message' => 'Budget hiburan hampir habis bulan ini.'],
    ]);

    $response = $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm")->assertOk();

    $response->assertJsonPath('data.status', 'confirmed');
    $response->assertJsonPath('data.result_table', null);
    $response->assertJsonPath('data.result_id', null);
});

test('rejecting a draft marks it rejected and writes nothing', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $account = Account::factory()->for($family)->create(['opening_balance' => 1_000_000, 'current_balance' => 1_000_000]);
    $wallet = Wallet::factory()->for($family)->create();
    $message = draftMessage($family, $member);
    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create([
        'action' => 'create_transaction',
        'payload' => [
            'type' => 'expense', 'amount' => 20000, 'transaction_date' => now()->toDateString(),
            'account_id' => $account->id, 'wallet_id' => $wallet->id,
            'source_id' => null, 'to_account_id' => null, 'goal_id' => null,
        ],
    ]);

    $response = $this->postJson("/api/v1/ai-actions/{$aiAction->id}/reject")->assertOk();

    $response->assertJsonPath('data.status', 'rejected');
    expect(Transaction::query()->count())->toBe(0);
    expect($account->fresh()->current_balance)->toBe(1_000_000);
});

test('confirming an already-resolved draft fails', function () {
    [, $family, $member] = $this->actingAsFamilyMember('member');
    $message = draftMessage($family, $member);
    $aiAction = AiAction::factory()->confirmed()->for($family)->for($message, 'message')->create([
        'action' => 'advice',
        'payload' => ['message' => 'sudah dikonfirmasi'],
    ]);

    $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm")->assertStatus(422);
    $this->postJson("/api/v1/ai-actions/{$aiAction->id}/reject")->assertStatus(422);
});

test('viewer cannot confirm or reject', function () {
    [, $family, $member] = $this->actingAsFamilyMember('viewer');
    $message = draftMessage($family, $member);
    $aiAction = AiAction::factory()->for($family)->for($message, 'message')->create(['action' => 'advice']);

    $this->postJson("/api/v1/ai-actions/{$aiAction->id}/confirm")->assertStatus(403);
    $this->postJson("/api/v1/ai-actions/{$aiAction->id}/reject")->assertStatus(403);
});

test('cannot confirm or reject another familys draft', function () {
    $this->actingAsFamilyMember('admin');
    $otherFamily = Family::factory()->create();
    $otherMember = FamilyMember::factory()->for($otherFamily)->create();
    $otherMessage = draftMessage($otherFamily, $otherMember);
    $otherAction = AiAction::factory()->for($otherFamily)->for($otherMessage, 'message')->create(['action' => 'advice']);

    $this->postJson("/api/v1/ai-actions/{$otherAction->id}/confirm")->assertStatus(404);
    $this->postJson("/api/v1/ai-actions/{$otherAction->id}/reject")->assertStatus(404);
});
