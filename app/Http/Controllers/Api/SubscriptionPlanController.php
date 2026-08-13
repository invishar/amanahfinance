<?php

namespace App\Http\Controllers\Api;

use App\Actions\SubscriptionPlans\SubscriptionPlanActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSubscriptionPlanRequest;
use App\Http\Requests\StoreSubscriptionPlanRequest;
use App\Http\Requests\UpdateSubscriptionPlanRequest;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;

// Platform-wide catalog (see SubscriptionPlanPolicy): index/show are mounted
// as fully public routes (routes/api.php, no auth:sanctum at all) -- no
// authorize() call here since there is no user to gate against. Mutation is
// gated by users.is_platform_admin.
class SubscriptionPlanController extends Controller
{
    public function __construct(private SubscriptionPlanActions $actions) {}

    public function index(IndexSubscriptionPlanRequest $request)
    {
        return SubscriptionPlanResource::collection($this->actions->index($request->validated()));
    }

    public function store(StoreSubscriptionPlanRequest $request)
    {
        $plan = $this->actions->create($request->validated());

        return (new SubscriptionPlanResource($plan))->response()->setStatusCode(201);
    }

    public function show(SubscriptionPlan $subscriptionPlan)
    {
        return new SubscriptionPlanResource($subscriptionPlan);
    }

    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan)
    {
        $plan = $this->actions->update($subscriptionPlan, $request->validated());

        return new SubscriptionPlanResource($plan);
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        $this->authorize('delete', $subscriptionPlan);

        $this->actions->delete($subscriptionPlan);

        return response()->json(null, 204);
    }
}
