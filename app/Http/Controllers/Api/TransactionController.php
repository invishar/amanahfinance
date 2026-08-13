<?php

namespace App\Http\Controllers\Api;

use App\Actions\Transactions\TransactionActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;

class TransactionController extends Controller
{
    public function __construct(private TransactionActions $actions) {}

    public function index(IndexTransactionRequest $request)
    {
        $this->authorize('viewAny', Transaction::class);

        return TransactionResource::collection($this->actions->index($request->validated()));
    }

    public function store(StoreTransactionRequest $request)
    {
        $transaction = $this->actions->create($request->validated());

        return (new TransactionResource($transaction))->response()->setStatusCode(201);
    }

    public function show(Transaction $transaction)
    {
        $this->authorize('view', $transaction);

        return new TransactionResource($transaction);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $transaction = $this->actions->update($transaction, $request->validated());

        return new TransactionResource($transaction);
    }

    public function destroy(Transaction $transaction)
    {
        $this->authorize('delete', $transaction);

        $this->actions->delete($transaction);

        return response()->json(null, 204);
    }
}
