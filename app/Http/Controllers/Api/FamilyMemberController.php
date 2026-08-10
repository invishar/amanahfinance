<?php

namespace App\Http\Controllers\Api;

use App\Actions\Families\FamilyMemberActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFamilyMemberRequest;
use App\Http\Requests\UpdateFamilyMemberRequest;
use App\Http\Resources\FamilyMemberResource;
use App\Models\FamilyMember;

class FamilyMemberController extends Controller
{
    public function __construct(private FamilyMemberActions $actions) {}

    public function index()
    {
        $this->authorize('viewAny', FamilyMember::class);

        $members = FamilyMember::query()->with('user')->paginate(20);

        return FamilyMemberResource::collection($members);
    }

    public function store(StoreFamilyMemberRequest $request)
    {
        $member = $this->actions->create($request->validated());

        return (new FamilyMemberResource($member))->response()->setStatusCode(201);
    }

    public function show(FamilyMember $familyMember)
    {
        $this->authorize('view', $familyMember);

        return new FamilyMemberResource($familyMember->load('user'));
    }

    public function update(UpdateFamilyMemberRequest $request, FamilyMember $familyMember)
    {
        $familyMember = $this->actions->update($familyMember, $request->validated());

        return new FamilyMemberResource($familyMember);
    }

    public function destroy(FamilyMember $familyMember)
    {
        $this->authorize('delete', $familyMember);

        $this->actions->delete($familyMember);

        return response()->json(null, 204);
    }
}
