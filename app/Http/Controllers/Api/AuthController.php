<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\AuthActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateUserPreferencesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(private AuthActions $actions) {}

    public function register(RegisterRequest $request)
    {
        $result = $this->actions->register($request->validated());
        $this->openWebSession($request, $result['user']);

        return $this->tokenResponse($result, 201);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->actions->login($request->validated());
        $this->openWebSession($request, $result['user']);

        return $this->tokenResponse($result, 200);
    }

    public function logout(Request $request)
    {
        // Autentikasi lewat sesi memberi TransientToken, bukan baris di
        // personal_access_tokens -- tidak ada yang bisa dihapus di sana.
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $this->closeWebSession($request);

        return response()->json(null, 204);
    }

    /* --- Sesi cookie ------------------------------------------------------
       Request yang datang dari halaman aplikasi sendiri (same-origin) lewat
       EnsureFrontendRequestsAreStateful, jadi punya sesi. Untuk request itu
       login juga membuka sesi cookie, supaya klien tidak perlu menyimpan
       Bearer token di localStorage sama sekali. Klien lain (mobile, skrip)
       tidak punya sesi di request-nya: cabang ini dilewati dan mereka tetap
       memakai `token` di response. Logika auth-nya sendiri tetap satu, di
       AuthActions -- ini murni soal cara sesi dibawa. */

    private function openWebSession(Request $request, User $user): void
    {
        if (! $request->hasSession()) {
            return;
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
    }

    private function closeWebSession(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user);
    }

    public function updatePreferences(UpdateUserPreferencesRequest $request)
    {
        /** @var User $user */
        $user = $request->user();
        $user->update($request->validated());

        return new UserResource($user->fresh());
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
