<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang halaman /admin. Komponen RequireAdmin di klien hanya mengatur apa
 * yang terlihat; yang benar-benar menutup aksesnya adalah middleware ini.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Akun ini tidak punya akses admin platform.');

        return $next($request);
    }
}
