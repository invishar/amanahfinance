<?php

use Illuminate\Support\Facades\Route;

Route::get('/docs', function () {
    return view('docs');
});

// Fallback for the frontend static export. nginx (Herd locally, likely also
// hPanel) resolves literal file paths under public/ directly but does NOT
// auto-append `.html` or resolve `dir/index.html` for extensionless clean
// URLs ('/', '/login', ...) -- confirmed by manual testing, contradicting
// the earlier assumption that no catch-all route would be needed. Only
// fires when nothing else matched (route priority is lowest), so it never
// shadows '/api/*' or '/docs'.
Route::fallback(function () {
    if (request()->is('api/*')) {
        abort(404);
    }

    $path = trim(request()->path(), '/');
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
