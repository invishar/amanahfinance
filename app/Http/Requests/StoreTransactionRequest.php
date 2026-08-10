<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TransactionRules;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    use TransactionRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Transaction::class);
    }

    // origin/created_by are never accepted from the client: manual API
    // entries are always origin=manual, created_by=current member (see Action).
    public function rules(): array
    {
        return $this->transactionRules(partial: false);
    }
}
