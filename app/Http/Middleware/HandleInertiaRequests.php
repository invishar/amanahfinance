<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Sengaja hanya identitas sesi, bukan data bisnis: siapa yang sedang
     * login menentukan apa yang boleh dirender shell (dan itu sudah diketahui
     * server saat halaman dibuat), sementara saldo, family, transaksi, dan
     * seterusnya tetap diambil klien dari /api/v1. Menaruh data bisnis di
     * sini akan bikin satu angka punya dua sumber yang bisa berbeda.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Guard 'web' (sesi cookie) -- halaman Inertia tidak pernah dipanggil
        // dengan Bearer token.
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                ] : null,
            ],
        ];
    }
}
