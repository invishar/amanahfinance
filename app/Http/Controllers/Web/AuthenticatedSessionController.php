<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Auth berbasis sesi cookie untuk halaman Inertia.
 *
 * Ini terpisah dari App\Http\Controllers\Api\AuthController yang stateless
 * (Bearer token Sanctum) dan tetap dipakai /api/v1/*. Dua pintu masuk,
 * satu tabel users — tidak ada yang perlu diubah di sisi API.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $field = filled($data['email'] ?? null) ? 'email' : 'phone';

        $this->ensureIsNotRateLimited($request, $data[$field]);

        $credentials = [
            $field => $data[$field],
            'password' => $data['password'],
        ];

        // Auth::attempt mencocokkan password lewat User::getAuthPassword(),
        // yang menunjuk ke kolom password_hash (bukan `password`).
        if (! Auth::guard('web')->attempt($credentials, (bool) ($data['remember'] ?? false))) {
            RateLimiter::hit($this->throttleKey($request, $data[$field]));

            // Pesan generik dan digantung di satu field: sama seperti alur
            // API, tidak membedakan "user tidak ada" vs "password salah"
            // untuk mencegah user enumeration.
            throw ValidationException::withMessages([
                $field => 'Email/telepon atau kata sandi salah.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $data[$field]));

        // Wajib: bikin id sesi baru supaya sesi tamu sebelumnya tidak bisa
        // dipakai lagi (session fixation).
        $request->session()->regenerate();

        Auth::guard('web')->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function ensureIsNotRateLimited(Request $request, string $identifier): void
    {
        $key = $this->throttleKey($request, $identifier);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '
                .RateLimiter::availableIn($key).' detik.',
        ]);
    }

    private function throttleKey(Request $request, string $identifier): string
    {
        return 'login|'.mb_strtolower($identifier).'|'.$request->ip();
    }
}
