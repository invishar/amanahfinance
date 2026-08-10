<?php

namespace App\Http\Controllers\Api;

use App\Actions\Wallets\WalletActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;

class WalletController extends Controller
{
    public function __construct(private WalletActions $actions) {}

    public function index()
    {
        $this->authorize('viewAny', Wallet::class);

        return WalletResource::collection(Wallet::query()->orderBy('sort_order')->paginate(20));
    }

    public function store(StoreWalletRequest $request)
    {
        $wallet = $this->actions->create($request->validated());

        return (new WalletResource($wallet))->response()->setStatusCode(201);
    }

    public function show(Wallet $wallet)
    {
        $this->authorize('view', $wallet);

        return new WalletResource($wallet);
    }

    public function update(UpdateWalletRequest $request, Wallet $wallet)
    {
        $wallet = $this->actions->update($wallet, $request->validated());

        return new WalletResource($wallet);
    }

    public function destroy(Wallet $wallet)
    {
        $this->authorize('delete', $wallet);

        $this->actions->delete($wallet);

        return response()->json(null, 204);
    }
}
