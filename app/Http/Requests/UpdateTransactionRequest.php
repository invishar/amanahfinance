<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TransactionRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    use TransactionRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('transaction'));
    }

    public function rules(): array
    {
        return $this->transactionRules(partial: true);
    }
}
