<?php

namespace App\Actions\AiActions;

use App\Actions\Accounts\AccountActions;
use App\Actions\IncomeSources\IncomeSourceActions;
use App\Actions\SavingsGoals\SavingsGoalActions;
use App\Actions\Transactions\TransactionActions;
use App\Actions\Wallets\WalletActions;
use App\Models\AiAction;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// The only place ai_actions rows ever get mutated, and the only place a
// draft turns into a real business-table row (aturan #5). $edits lets the
// user tweak the draft inline before confirming (status becomes 'edited'
// instead of 'confirmed') -- ids in $edits are still validated against this
// action's own family, never trusted blindly from the client.
class ConfirmAiAction
{
    public function __construct(
        private TransactionActions $transactions,
        private WalletActions $wallets,
        private AccountActions $accounts,
        private IncomeSourceActions $incomeSources,
        private SavingsGoalActions $savingsGoals,
    ) {}

    public function confirm(AiAction $aiAction, array $edits = []): AiAction
    {
        $this->guardPending($aiAction);

        $payload = array_merge($aiAction->payload, $edits);
        $this->validatePayload($aiAction->family_id, $aiAction->action, $payload);

        return DB::transaction(function () use ($aiAction, $payload, $edits) {
            [$table, $id] = $this->write($aiAction, $payload);

            $aiAction->update([
                'status' => $edits === [] ? 'confirmed' : 'edited',
                'result_table' => $table,
                'result_id' => $id,
                'resolved_at' => now(),
                'resolved_by' => app(CurrentFamily::class)->memberId(),
            ]);

            return $aiAction->fresh();
        });
    }

    public function reject(AiAction $aiAction): AiAction
    {
        $this->guardPending($aiAction);

        $aiAction->update([
            'status' => 'rejected',
            'resolved_at' => now(),
            'resolved_by' => app(CurrentFamily::class)->memberId(),
        ]);

        return $aiAction->fresh();
    }

    private function guardPending(AiAction $aiAction): void
    {
        if ($aiAction->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ["Draft ini sudah berstatus \"{$aiAction->status}\", tidak bisa diproses lagi."],
            ]);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string} [result_table, result_id]
     */
    private function write(AiAction $aiAction, array $payload): array
    {
        $payload = [...$payload, 'family_id' => $aiAction->family_id];

        if ($aiAction->action === 'create_transaction') {
            $payload['origin'] = $this->transactionOrigin($aiAction);
        }

        // Drop explicit nulls (NameResolver/the model leave fields blank when
        // unsure, per CLAUDE.md) so DB-level defaults apply -- e.g. wallets.icon
        // is NOT NULL DEFAULT 'wallet', so an explicit null would fail the
        // insert instead of falling back like an omitted key would.
        $payload = array_filter($payload, fn ($value) => $value !== null);

        return match ($aiAction->action) {
            'create_transaction' => ['transactions', $this->transactions->create($payload)->id],
            'create_wallet' => ['wallets', $this->wallets->create($payload)->id],
            'create_account' => ['accounts', $this->accounts->create($payload)->id],
            'create_income_source' => ['income_sources', $this->incomeSources->create($payload)->id],
            'create_savings_goal' => ['savings_goals', $this->savingsGoals->create($payload)->id],
            // advice is informational only -- confirming it just acknowledges
            // the card, there is no business row to write.
            'advice' => [null, null],
        };
    }

    // transactions_origin_ck distinguishes how a transaction entered the
    // system; AI-confirmed ones must not collapse into 'manual'.
    private function transactionOrigin(AiAction $aiAction): string
    {
        return match ($aiAction->message?->input_mode) {
            'voice' => 'chat_voice',
            'image' => 'receipt_ocr',
            default => 'chat_text',
        };
    }

    private function validatePayload(string $familyId, string $action, array $payload): void
    {
        $scoped = fn (string $table) => Rule::exists($table, 'id')->where('family_id', $familyId);

        $rules = match ($action) {
            'create_transaction' => [
                'type' => ['required', Rule::in(['income', 'expense', 'transfer', 'savings'])],
                'amount' => ['required', 'integer', 'min:1'],
                'transaction_date' => ['required', 'date'],
                'account_id' => ['required', 'uuid', $scoped('accounts')],
                'to_account_id' => [
                    Rule::requiredIf(($payload['type'] ?? null) === 'transfer'),
                    'nullable', 'uuid', $scoped('accounts'), 'different:account_id',
                ],
                'wallet_id' => [
                    Rule::requiredIf(($payload['type'] ?? null) === 'expense'),
                    'nullable', 'uuid', $scoped('wallets'),
                ],
                'source_id' => [
                    Rule::requiredIf(($payload['type'] ?? null) === 'income'),
                    'nullable', 'uuid', $scoped('income_sources'),
                ],
                'goal_id' => [
                    Rule::requiredIf(($payload['type'] ?? null) === 'savings'),
                    'nullable', 'uuid', $scoped('savings_goals'),
                ],
                'note' => ['nullable', 'string'],
            ],
            'create_wallet' => [
                'name' => ['required', 'string'],
                'icon' => ['nullable', 'string'],
                'color' => ['nullable', 'string'],
                'monthly_budget' => ['nullable', 'integer', 'min:0'],
            ],
            'create_account' => [
                'name' => ['required', 'string'],
                'account_type' => ['required', Rule::in(['bank', 'ewallet', 'cash', 'other'])],
                'institution' => ['nullable', 'string'],
                'opening_balance' => ['nullable', 'integer', 'min:0'],
            ],
            'create_income_source' => [
                'name' => ['required', 'string'],
                'expected_amount' => ['nullable', 'integer', 'min:0'],
                'cadence' => ['nullable', Rule::in(['monthly', 'biweekly', 'weekly', 'irregular'])],
            ],
            'create_savings_goal' => [
                'target_name' => ['required', 'string'],
                'target_amount' => ['required', 'integer', 'min:1'],
                'deadline' => ['nullable', 'date'],
                'account_id' => ['nullable', 'uuid', $scoped('accounts')],
            ],
            'advice' => [
                'message' => ['required', 'string'],
            ],
        };

        Validator::make($payload, $rules)->validate();
    }
}
