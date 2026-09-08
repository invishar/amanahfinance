<?php

use Illuminate\Support\Facades\Route;

Route::get('/docs', function () {
    return view('docs');
});

/*
|--------------------------------------------------------------------------
| Halaman Inertia
|--------------------------------------------------------------------------
|
| Satu route per halaman, menggantikan routing berbasis folder Next
| (tiap `page.tsx` di bawah `frontend/app`). Nama komponen di argumen kedua
| menunjuk file di resources/js/Pages, dan awalannya juga yang menentukan
| layout - `App/*` memakai shell aplikasi, `Admin/*` memakai shell admin
| (lihat resolve() di resources/js/app.tsx).
|
| Inertia di sini HANYA lapisan frontend: tidak ada satu pun route di bawah
| yang mengirim data lewat props atau menulis apa pun. Seluruh baca/tulis
| tetap lewat /api/v1 dari klien (TanStack Query di resources/js/lib/api).
| Yang dikerjakan server cuma dua: memilih komponen mana yang dirender, dan
| menutup halaman yang butuh sesi. Auth sendiri tetap milik API - login,
| daftar, dan keluar semuanya memanggil /api/v1/auth/*, yang sekaligus
| membuka sesi cookie untuk request same-origin (lihat AuthController).
|
*/

Route::inertia('/', 'Home')->name('home');

// Halaman auth hanya dirender di sini; form-nya sendiri menembak API.
// Middleware `guest` mencegah user yang sudah punya sesi membuka ulang
// halaman login.
Route::middleware('guest')->group(function () {
    Route::inertia('/login', 'Auth/Login')->name('login');
    Route::inertia('/register', 'Auth/Register')->name('register');
    Route::inertia('/admin/login', 'Admin/Login')->name('admin.login');
});

Route::middleware('auth')->group(function () {
    // Di luar grup aplikasi: user yang belum punya family justru diarahkan
    // ke sini oleh RequireSession, jadi halaman ini tidak boleh ikut
    // mensyaratkan family.
    Route::inertia('/onboarding', 'Auth/Onboarding')->name('onboarding');

    Route::inertia('/chat', 'App/Chat')->name('chat');
    Route::inertia('/dashboard', 'App/Dashboard')->name('dashboard');
    Route::inertia('/transactions', 'App/Transactions')->name('transactions');
    Route::inertia('/wallets', 'App/Wallets')->name('wallets');
    Route::inertia('/accounts', 'App/Accounts')->name('accounts');
    Route::inertia('/income', 'App/Income')->name('income');
    Route::inertia('/goals', 'App/Goals')->name('goals');
    Route::inertia('/analysis', 'App/Analysis')->name('analysis');
    Route::inertia('/settings', 'App/Settings')->name('settings');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::inertia('/', 'Admin/Dashboard')->name('dashboard');
        Route::inertia('/users', 'Admin/Users')->name('users');
        Route::inertia('/payments', 'Admin/Payments')->name('payments');
        Route::inertia('/llm-settings', 'Admin/LlmSettings')->name('llm-settings');
        Route::inertia('/ai-errors', 'Admin/AiErrors')->name('ai-errors');
        Route::inertia('/ai-logs', 'Admin/AiLogs')->name('ai-logs');
    });
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
