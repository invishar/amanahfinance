<?php

use Illuminate\Support\Facades\Route;

Route::get('/docs', function () {
    return view('docs');
});

// Fallback for the frontend static export. Requests for extensionless clean
// URLs ('/', '/login', ...) never auto-append `.html` or resolve
// `dir/index.html` on their own -- confirmed by manual testing both locally
// and on hPanel. Only fires when nothing else matched (route priority is
// lowest), so it never shadows '/api/*' or '/docs'.
//
// hPanel's edge (Hostinger's `hcdn`) does not reliably bypass PHP for every
// literal static file under public/ (_next/static/*.css, *.js, fonts, ...) --
// some assets reach this route directly rather than being served by the web
// server/CDN, unlike what local Herd testing suggested. So the exact-path
// check below is load-bearing in production, not just a local convenience.
Route::fallback(function () {
    if (request()->is('api/*')) {
        abort(404);
    }

    $path = trim(request()->path(), '/');

    if ($path !== '') {
        $exact = public_path($path);
        if (is_file($exact)) {
            return response()->file($exact);
        }
    }

    $candidates = $path === ''
        ? ['index.html']
        : ["{$path}.html", "{$path}/index.html"];

    foreach ($candidates as $candidate) {
        $file = public_path($candidate);
        if (is_file($file)) {
            return response(file_get_contents($file), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }
    }

    abort(404);
});
