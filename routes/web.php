<?php

use App\Http\Controllers\Web\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/docs', function () {
    return view('docs');
});

// Halaman Inertia + React. Sejak fallback static export di bawah
// dinonaktifkan, route di sini yang melayani seluruh halaman web.
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Auth berbasis sesi cookie. Terpisah dari /api/v1/auth/* yang stateless
// (Bearer token Sanctum) dan tetap dipakai klien lain.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

// =====================================================================
// DINONAKTIFKAN 7 September 2026 — frontend pindah ke Inertia + React.
//
// Blok di bawah ini SENGAJA dikomentari, bukan dihapus: folder `frontend/`
// (Next.js static export) masih ada di repo dan bisa dihidupkan lagi kapan
// saja dengan menghapus komentar blok ini. Selama masih dikomentari,
// static export tidak dilayani sama sekali dan seluruh halaman dirender
// Inertia lewat route eksplisit di atas.
//
// Catatan kalau nanti dihidupkan lagi: Route::fallback() punya prioritas
// paling rendah, jadi ia tidak akan menutupi route Inertia di atas — tapi
// ia akan menangkap `/` kalau route `/` di atas ikut dihapus.
// =====================================================================
// // Fallback for the frontend static export. Requests for extensionless clean
// // URLs ('/', '/login', ...) never auto-append `.html` or resolve
// // `dir/index.html` on their own -- confirmed by manual testing both locally
// // and on hPanel. Only fires when nothing else matched (route priority is
// // lowest), so it never shadows '/api/*' or '/docs'.
// //
// // hPanel's edge (Hostinger's `hcdn`) does not reliably bypass PHP for every
// // literal static file under public/ (_next/static/*.css, *.js, fonts, ...) --
// // some assets reach this route directly rather than being served by the web
// // server/CDN, unlike what local Herd testing suggested. So the exact-path
// // check below is load-bearing in production, not just a local convenience.
// //
// // Content-Type is set from a fixed extension map rather than
// // response()->file()'s automatic guessing: that relies on the `fileinfo`
// // PHP extension, which hPanel has previously lost outside the panel's own
// // PHP config (see the composer2/composer-php.ini gotcha in CLAUDE.md) --
// // when it's missing, Symfony's guesser silently falls back to text/plain,
// // and browsers refuse to apply a stylesheet or execute a script served
// // with that Content-Type.
// Route::fallback(function () {
//     if (request()->is('api/*')) {
//         abort(404);
//     }
//
//     $path = trim(request()->path(), '/');
//
//     $mimeTypes = [
//         'css' => 'text/css',
//         'js' => 'application/javascript',
//         'mjs' => 'application/javascript',
//         'json' => 'application/json',
//         'map' => 'application/json',
//         'svg' => 'image/svg+xml',
//         'png' => 'image/png',
//         'jpg' => 'image/jpeg',
//         'jpeg' => 'image/jpeg',
//         'gif' => 'image/gif',
//         'webp' => 'image/webp',
//         'ico' => 'image/x-icon',
//         'woff' => 'font/woff',
//         'woff2' => 'font/woff2',
//         'ttf' => 'font/ttf',
//         'otf' => 'font/otf',
//         'txt' => 'text/plain',
//         'xml' => 'application/xml',
//         'webmanifest' => 'application/manifest+json',
//     ];
//
//     if ($path !== '') {
//         $exact = public_path($path);
//         if (is_file($exact)) {
//             $extension = strtolower(pathinfo($exact, PATHINFO_EXTENSION));
//
//             return response()->file($exact, [
//                 'Content-Type' => $mimeTypes[$extension] ?? 'application/octet-stream',
//             ]);
//         }
//     }
//
//     $candidates = $path === ''
//         ? ['index.html']
//         : ["{$path}.html", "{$path}/index.html"];
//
//     foreach ($candidates as $candidate) {
//         $file = public_path($candidate);
//         if (is_file($file)) {
//             return response(file_get_contents($file), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
//         }
//     }
//
//     abort(404);
// });
