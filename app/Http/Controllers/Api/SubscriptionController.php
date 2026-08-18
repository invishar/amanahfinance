<?php

namespace App\Http\Controllers\Api;

use App\Actions\Subscriptions\SubscriptionActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateSubscriptionRequest;
use App\Http\Requests\IndexSubscriptionRequest;
use App\Http\Requests\RejectSubscriptionRequest;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;

// index/show/store act on the caller's own family (resolve.family). activate/
// reject are mounted OUTSIDE resolve.family (see routes/api.php) -- a platform
// admin reviews every family's requests, never just their own (aturan #3 spirit:
// family_id still never comes from the body, but the read/write here is
// intentionally cross-tenant, gated by users.is_admin instead).
class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionActions $actions) {}

    public function index(IndexSubscriptionRequest $request)
    {
        $this->authorize('viewAny', Subscription::class);

        return SubscriptionResource::collection($this->actions->index($request->validated()));
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $subscription = $this->actions->create($request->validated());

        return (new SubscriptionResource($subscription))->response()->setStatusCode(201);
    }

    public function show(Subscription $subscription)
    {
        $this->authorize('view', $subscription);

        return new SubscriptionResource($subscription);
    }

    // Cross-family admin queue -- same query shape as index(), but Subscription's
    // BelongsToFamily scope no-ops here because this route sits outside
    // resolve.family (see routes/api.php), so every family's rows come back.
    public function adminIndex(IndexSubscriptionRequest $request)
    {
        $this->authorize('reviewAny', Subscription::class);

        return SubscriptionResource::collection($this->actions->index($request->validated()));
    }

    public function activate(ActivateSubscriptionRequest $request, Subscription $subscription)
    {
        return new SubscriptionResource($this->actions->activate($subscription));
    }

    public function reject(RejectSubscriptionRequest $request, Subscription $subscription)
    {
        return new SubscriptionResource($this->actions->reject($subscription, $request->validated()['review_note']));
    }
}
