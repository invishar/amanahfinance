<?php

namespace App\Http\Controllers\Api;

use App\Actions\Families\FamilyInviteActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFamilyInviteRequest;
use App\Http\Requests\UpdateFamilyInviteRequest;
use App\Http\Resources\FamilyInviteResource;
use App\Models\FamilyInvite;
use Illuminate\Http\Request;

class FamilyInviteController extends Controller
{
    public function __construct(private FamilyInviteActions $actions) {}

    public function index()
    {
        $this->authorize('viewAny', FamilyInvite::class);

        return FamilyInviteResource::collection(FamilyInvite::query()->paginate(20));
    }

    public function store(StoreFamilyInviteRequest $request)
    {
        $invite = $this->actions->create($request->user(), $request->validated());

        return (new FamilyInviteResource($invite))->response()->setStatusCode(201);
    }

    public function show(FamilyInvite $familyInvite)
    {
        $this->authorize('view', $familyInvite);

        return new FamilyInviteResource($familyInvite);
    }

    public function update(UpdateFamilyInviteRequest $request, FamilyInvite $familyInvite)
    {
        $familyInvite = $this->actions->update($familyInvite, $request->validated());

        return new FamilyInviteResource($familyInvite);
    }

    public function destroy(FamilyInvite $familyInvite)
    {
        $this->authorize('delete', $familyInvite);

        $this->actions->delete($familyInvite);

        return response()->json(null, 204);
    }
}
