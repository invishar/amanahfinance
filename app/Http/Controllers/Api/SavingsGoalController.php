<?php

namespace App\Http\Controllers\Api;

use App\Actions\SavingsGoals\SavingsGoalActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSavingsGoalRequest;
use App\Http\Requests\StoreSavingsGoalRequest;
use App\Http\Requests\UpdateSavingsGoalRequest;
use App\Http\Resources\SavingsGoalResource;
use App\Models\SavingsGoal;

class SavingsGoalController extends Controller
{
    public function __construct(private SavingsGoalActions $actions) {}

    public function index(IndexSavingsGoalRequest $request)
    {
        $this->authorize('viewAny', SavingsGoal::class);

        return SavingsGoalResource::collection($this->actions->index($request->validated()));
    }

    public function store(StoreSavingsGoalRequest $request)
    {
        $savingsGoal = $this->actions->create($request->validated());

        return (new SavingsGoalResource($savingsGoal))->response()->setStatusCode(201);
    }

    public function show(SavingsGoal $savingsGoal)
    {
        $this->authorize('view', $savingsGoal);

        return new SavingsGoalResource($savingsGoal);
    }

    public function update(UpdateSavingsGoalRequest $request, SavingsGoal $savingsGoal)
    {
        $savingsGoal = $this->actions->update($savingsGoal, $request->validated());

        return new SavingsGoalResource($savingsGoal);
    }

    public function destroy(SavingsGoal $savingsGoal)
    {
        $this->authorize('delete', $savingsGoal);

        $this->actions->delete($savingsGoal);

        return response()->json(null, 204);
    }
}
