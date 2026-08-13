<?php

namespace App\Http\Controllers\Api;

use App\Actions\Families\FamilyInviteActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptFamilyInviteRequest;
use App\Http\Requests\StoreFamilyInviteRequest;
use App\Http\Requests\UpdateFamilyInviteRequest;
use App\Http\Resources\FamilyInviteResource;
use App\Http\Resources\FamilyMemberResource;
use App\Models\FamilyInvite;

class FamilyInviteController extends Controller
{
    public function __construct(private FamilyInviteActions $actions) {}

    // Deliberately outside resolve.family: the user accepting has no
    // membership in the target family yet -- see routes/api.php.
    public function accept(AcceptFamilyInviteRequest $request)
    {
        $member = $this->actions->accept($request->user(), $request->validated()['token']);

        return (new FamilyMemberResource($member))->response()->setStatusCode(201);
    }

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
