<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\AuthActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthActions $actions) {}

    public function register(RegisterRequest $request)
    {
        return $this->tokenResponse($this->actions->register($request->validated()), 201);
    }

    public function login(LoginRequest $request)
    {
        return $this->tokenResponse($this->actions->login($request->validated()), 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user);
    }

    private function tokenResponse(array $result, int $status)
    {
        return response()->json([
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
        ], $status);
    }
}
