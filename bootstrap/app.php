<?php

use App\Exceptions\ConflictException;
use App\Http\Middleware\ResolveFamily;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'resolve.family' => ResolveFamily::class,
        ]);

        // ResolveFamily must run before route model binding, otherwise the
        // BelongsToFamily global scope has no family_id to filter by yet.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveFamily::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(fn (ConflictException $e) => response()->json([
            'message' => $e->getMessage(),
        ], 409));
    })->create();
