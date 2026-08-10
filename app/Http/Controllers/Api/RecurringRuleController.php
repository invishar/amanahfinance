<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecurringRules\RecurringRuleActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecurringRuleRequest;
use App\Http\Requests\UpdateRecurringRuleRequest;
use App\Http\Resources\RecurringRuleResource;
use App\Models\RecurringRule;

class RecurringRuleController extends Controller
{
    public function __construct(private RecurringRuleActions $actions) {}

    public function index()
    {
        $this->authorize('viewAny', RecurringRule::class);

        return RecurringRuleResource::collection(RecurringRule::query()->paginate(20));
    }

    public function store(StoreRecurringRuleRequest $request)
    {
        $recurringRule = $this->actions->create($request->validated());

        return (new RecurringRuleResource($recurringRule))->response()->setStatusCode(201);
    }

    public function show(RecurringRule $recurringRule)
    {
        $this->authorize('view', $recurringRule);

        return new RecurringRuleResource($recurringRule);
    }

    public function update(UpdateRecurringRuleRequest $request, RecurringRule $recurringRule)
    {
        $recurringRule = $this->actions->update($recurringRule, $request->validated());

        return new RecurringRuleResource($recurringRule);
    }

    public function destroy(RecurringRule $recurringRule)
    {
        $this->authorize('delete', $recurringRule);

        $this->actions->delete($recurringRule);

        return response()->json(null, 204);
    }
}
