<?php

namespace App\Http\Controllers\Api;

use App\Actions\Wallets\WalletBudgetActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWalletBudgetRequest;
use App\Http\Requests\UpdateWalletBudgetRequest;
use App\Http\Resources\WalletBudgetResource;
use App\Models\Wallet;
use App\Models\WalletBudget;

class WalletBudgetController extends Controller
{
    public function __construct(private WalletBudgetActions $actions) {}

    public function index(Wallet $wallet)
    {
        $this->authorize('view', $wallet);
        $this->authorize('viewAny', WalletBudget::class);

        return WalletBudgetResource::collection($wallet->budgets()->paginate(20));
    }

    public function store(StoreWalletBudgetRequest $request, Wallet $wallet)
    {
        $this->authorize('view', $wallet);

        $budget = $this->actions->create($wallet, $request->validated());

        return (new WalletBudgetResource($budget))->response()->setStatusCode(201);
    }

    public function show(WalletBudget $budget)
    {
        $this->authorize('view', $budget);

        return new WalletBudgetResource($budget);
    }

    public function update(UpdateWalletBudgetRequest $request, WalletBudget $budget)
    {
        $budget = $this->actions->update($budget, $request->validated());

        return new WalletBudgetResource($budget);
    }

    public function destroy(WalletBudget $budget)
    {
        $this->authorize('delete', $budget);

        $this->actions->delete($budget);

        return response()->json(null, 204);
    }
}
