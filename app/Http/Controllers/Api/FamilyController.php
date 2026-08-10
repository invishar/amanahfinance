<?php

namespace App\Http\Controllers\Api;

use App\Actions\Families\FamilyActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFamilyRequest;
use App\Http\Requests\UpdateFamilyRequest;
use App\Http\Resources\FamilyResource;
use App\Models\Family;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    public function __construct(private FamilyActions $actions) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Family::class);

        $families = Family::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id)->whereNull('removed_at'))
            ->paginate(20);

        return FamilyResource::collection($families);
    }

    public function store(StoreFamilyRequest $request)
    {
        $family = $this->actions->create($request->user(), $request->validated());

        return (new FamilyResource($family))->response()->setStatusCode(201);
    }

    public function show(Family $family)
    {
        $this->authorize('view', $family);

        return new FamilyResource($family);
    }

    public function update(UpdateFamilyRequest $request, Family $family)
    {
        $family = $this->actions->update($family, $request->validated());

        return new FamilyResource($family);
    }

    public function destroy(Family $family)
    {
        $this->authorize('delete', $family);

        $this->actions->delete($family);

        return response()->json(null, 204);
    }
}
