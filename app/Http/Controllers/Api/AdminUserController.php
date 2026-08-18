<?php

namespace App\Http\Controllers\Api;

use App\Actions\Users\AdminUserActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAdminUserRequest;
use App\Http\Resources\AdminUserDetailResource;
use App\Http\Resources\AdminUserResource;
use App\Models\User;

// Platform admin's user directory -- cross-family by design, gated by
// users.is_admin (UserPolicy), never family_members.role. Deliberately
// read-only: no store/update/destroy -- granting/revoking is_admin itself
// stays tinker-only (see User model docblock), not exposed via API.
class AdminUserController extends Controller
{
    public function __construct(private AdminUserActions $actions) {}

    public function index(IndexAdminUserRequest $request)
    {
        $this->authorize('viewAny', User::class);

        return AdminUserResource::collection($this->actions->index($request->validated()));
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return new AdminUserDetailResource($this->actions->show($user));
    }
}
