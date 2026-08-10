<?php

namespace App\Http\Middleware;

use App\Models\FamilyMember;
use App\Support\CurrentFamily;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which family the request acts on from the authenticated user's
 * own memberships. family_id NEVER comes from the request body -- X-Family-Id
 * only picks among families the user already belongs to.
 */
class ResolveFamily
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // withoutGlobalScope('family'): this lookup is what DEFINES the
        // current family for this request, so it must not be filtered by
        // whatever family was current on a *previous* request that reused
        // this container (happens under testing and Octane) -- otherwise a
        // user's other memberships become invisible to X-Family-Id.
        $memberships = FamilyMember::query()
            ->withoutGlobalScope('family')
            ->with('family')
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->get();

        if ($memberships->isEmpty()) {
            return response()->json([
                'message' => 'Akun ini belum tergabung dalam family manapun.',
            ], 403);
        }

        $requestedFamilyId = $request->header('X-Family-Id');

        $member = $requestedFamilyId
            ? $memberships->firstWhere('family_id', $requestedFamilyId)
            : $memberships->first();

        if (! $member) {
            return response()->json([
                'message' => 'X-Family-Id tidak valid atau bukan milik akun ini.',
            ], 403);
        }

        app(CurrentFamily::class)->set($member->family, $member);

        return $next($request);
    }
}
