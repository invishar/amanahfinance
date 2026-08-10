<?php

namespace App\Http\Controllers\Api;

use App\Actions\Accounts\AccountActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;

class AccountController extends Controller
{
    public function __construct(private AccountActions $actions) {}

    public function index()
    {
        $this->authorize('viewAny', Account::class);

        return AccountResource::collection(Account::query()->orderBy('sort_order')->paginate(20));
    }

    public function store(StoreAccountRequest $request)
    {
        $account = $this->actions->create($request->validated());

        return (new AccountResource($account))->response()->setStatusCode(201);
    }

    public function show(Account $account)
    {
        $this->authorize('view', $account);

        return new AccountResource($account);
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $account = $this->actions->update($account, $request->validated());

        return new AccountResource($account);
    }

    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        $this->actions->delete($account);

        return response()->json(null, 204);
    }
}
