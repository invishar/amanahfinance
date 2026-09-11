<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;

/**
 * SSR di sini cuma soal siapa yang menghasilkan HTML pertama -- data bisnis
 * tetap datang dari /api/v1 sesudah hidrasi. Jadi yang diuji bukan isi
 * halamannya, melainkan tiga janji infrastrukturnya: hasil render Node
 * dipakai, kegagalannya tidak pernah mematikan halaman, dan dev server Vite
 * tidak ikut ditembak.
 */
beforeEach(function () {
    config([
        'inertia.ssr.enabled' => true,
        // Test tidak ikut membangun bundle Node; yang diuji jalur HTTP-nya.
        'inertia.ssr.ensure_bundle_exists' => false,
    ]);
});

test('server-rendered html and head replace the client-side fallback', function () {
    Http::fake(['*' => Http::response([
        'head' => ['<title data-inertia="">Masuk — AmanaFinance</title>'],
        'body' => '<div id="app" data-server-rendered="true">HALAMAN DARI NODE</div>',
    ])]);

    $response = $this->get('/login')->assertOk();

    $response->assertSee('HALAMAN DARI NODE', false);
    $response->assertSee('<title data-inertia="">Masuk — AmanaFinance</title>', false);
    // Judul statis di app.blade.php hanya cadangan: kalau ikut terkirim, ia
    // berdiri lebih dulu di <head> dan browser memakai judul yang salah.
    $response->assertDontSee('<title inertia>', false);
});

test('a dead ssr process falls back to client rendering and is logged', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('connection refused')]);
    Log::spy();

    $response = $this->get('/login')->assertOk();

    $response->assertSee('<div id="app"></div>', false);
    $response->assertSee('<title inertia>', false);

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => str_contains($message, 'Inertia SSR gagal')
            && $context['component'] === 'Auth/Login'
            && $context['type'] === 'connection',
    );
});

test('ssr is skipped while vite runs hot', function () {
    // Dev server Vite di repo ini tidak menyediakan endpoint /__inertia_ssr,
    // jadi selama `npm run dev` SSR harus dilewati sepenuhnya -- bukan dicoba
    // lalu gagal. Hot file dialihkan ke direktori test supaya `npm run dev`
    // yang mungkin sedang jalan di mesin dev tidak ikut terpengaruh.
    $hotFile = storage_path('framework/testing/vite-hot');
    file_put_contents($hotFile, 'http://127.0.0.1:5173');
    Vite::useHotFile($hotFile);

    Http::fake();

    try {
        $this->get('/login')->assertOk()->assertSee('<div id="app"></div>', false);
    } finally {
        @unlink($hotFile);
    }

    Http::assertNothingSent();
});
