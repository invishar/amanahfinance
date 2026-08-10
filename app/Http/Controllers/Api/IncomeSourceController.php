<?php

namespace App\Http\Controllers\Api;

use App\Actions\IncomeSources\IncomeSourceActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncomeSourceRequest;
use App\Http\Requests\UpdateIncomeSourceRequest;
use App\Http\Resources\IncomeSourceResource;
use App\Models\IncomeSource;

class IncomeSourceController extends Controller
{
    public function __construct(private IncomeSourceActions $actions) {}

    public function index()
    {
        $this->authorize('viewAny', IncomeSource::class);

        return IncomeSourceResource::collection(IncomeSource::query()->paginate(20));
    }

    public function store(StoreIncomeSourceRequest $request)
    {
        $incomeSource = $this->actions->create($request->validated());

        return (new IncomeSourceResource($incomeSource))->response()->setStatusCode(201);
    }

    public function show(IncomeSource $incomeSource)
    {
        $this->authorize('view', $incomeSource);

        return new IncomeSourceResource($incomeSource);
    }

    public function update(UpdateIncomeSourceRequest $request, IncomeSource $incomeSource)
    {
        $incomeSource = $this->actions->update($incomeSource, $request->validated());

        return new IncomeSourceResource($incomeSource);
    }

    public function destroy(IncomeSource $incomeSource)
    {
        $this->authorize('delete', $incomeSource);

        $this->actions->delete($incomeSource);

        return response()->json(null, 204);
    }
}
