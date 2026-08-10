<?php

namespace App\Actions\RecurringRules;

use App\Models\RecurringRule;

class RecurringRuleActions
{
    public function create(array $data): RecurringRule
    {
        // fresh(): is_active/created_at have DB-level defaults create()
        // won't reflect when omitted.
        return RecurringRule::create($this->normalizeByType($data))->fresh();
    }

    public function update(RecurringRule $recurringRule, array $data): RecurringRule
    {
        $merged = array_merge($recurringRule->only([
            'type', 'wallet_id', 'source_id', 'account_id',
        ]), $data);

        $recurringRule->update($this->normalizeByType($merged));

        return $recurringRule->fresh();
    }

    public function delete(RecurringRule $recurringRule): void
    {
        $recurringRule->delete();
    }

    private function normalizeByType(array $data): array
    {
        $type = $data['type'];

        $data['wallet_id'] = $type === 'expense' ? ($data['wallet_id'] ?? null) : null;
        $data['source_id'] = $type === 'income' ? ($data['source_id'] ?? null) : null;
        $data['account_id'] = $type === 'savings' ? ($data['account_id'] ?? null) : null;

        return $data;
    }
}
