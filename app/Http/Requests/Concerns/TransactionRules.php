<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

// Mirrors the tx_*_ck CHECK constraints on the transactions table (see
// CLAUDE.md "Constraint transaksi") so client mistakes surface as 422s
// instead of a DB constraint violation.
trait TransactionRules
{
    protected function transactionRules(bool $partial): array
    {
        $maybeRequired = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'type' => [...$maybeRequired, Rule::in(['income', 'expense', 'transfer', 'savings'])],
            'amount' => [...$maybeRequired, 'integer', 'min:1'],
            'transaction_date' => [...$maybeRequired, 'date'],
            'account_id' => [...$maybeRequired, 'uuid', 'exists:accounts,id'],
            'to_account_id' => [
                Rule::requiredIf(fn () => $this->inputType() === 'transfer'),
                'nullable', 'uuid', 'exists:accounts,id', 'different:account_id',
            ],
            'wallet_id' => [
                Rule::requiredIf(fn () => $this->inputType() === 'expense'),
                'nullable', 'uuid', 'exists:wallets,id',
            ],
            'source_id' => [
                Rule::requiredIf(fn () => $this->inputType() === 'income'),
                'nullable', 'uuid', 'exists:income_sources,id',
            ],
            'goal_id' => [
                Rule::requiredIf(fn () => $this->inputType() === 'savings'),
                'nullable', 'uuid', 'exists:savings_goals,id',
            ],
            'note' => ['nullable', 'string'],
            'receipt_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    private function inputType(): ?string
    {
        return $this->input('type');
    }
}
