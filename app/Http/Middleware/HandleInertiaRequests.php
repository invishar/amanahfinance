<?php

namespace App\Http\Middleware;

use App\Models\FamilyMember;
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
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Guard 'web' (sesi cookie), bukan Sanctum -- halaman Inertia tidak
        // pernah dipanggil dengan Bearer token.
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ] : null,
                'family' => $user ? $this->currentFamily($request) : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }

    /**
     * Family aktif untuk sesi ini.
     *
     * Aturan #3 CLAUDE.md berlaku sama di web: family_id TIDAK PERNAH datang
     * dari request. Di API pemilihannya lewat header X-Family-Id; di web
     * lewat session('family_id'), dan keduanya hanya boleh memilih di antara
     * membership milik user itu sendiri -- nilai sesi yang tidak cocok
     * diabaikan, bukan dipercaya.
     *
     * @return array{id: string, name: string, role: string}|null
     */
    private function currentFamily(Request $request): ?array
    {
        $memberships = FamilyMember::query()
            ->withoutGlobalScope('family')
            ->with('family')
            ->where('user_id', $request->user()->id)
            ->whereNull('removed_at')
            ->get();

        if ($memberships->isEmpty()) {
            return null;
        }

        $selectedId = $request->session()->get('family_id');

        $member = ($selectedId ? $memberships->firstWhere('family_id', $selectedId) : null)
            ?? $memberships->first();

        return [
            'id' => $member->family_id,
            'name' => $member->family->name,
            'role' => $member->role,
        ];
    }
}
