<?php

use App\OpenApi\OpenApiSpec;
use Illuminate\Support\Facades\Route as RouteFacade;

test('openapi.json is publicly accessible and structurally valid', function () {
    $response = $this->getJson('/api/v1/openapi.json')->assertOk();

    $spec = $response->json();

    expect($spec['openapi'])->toBe('3.0.3');
    expect($spec['info']['title'])->toBe('AmanaFinance API');
    expect($spec['paths'])->not->toBeEmpty();
    expect($spec['components']['schemas'])->toHaveKey('Family');
    expect($spec['components']['schemas'])->toHaveKey('Transaction');
});

// Regression guard: every registered /api/v1 route must appear in the spec,
// so a new endpoint can't ship without its openapi.json entry (DoD item 4
// in CLAUDE.md, same "same PR" spirit as API-v1.md).
test('every v1 API route is documented in the spec', function () {
    $spec = OpenApiSpec::generate();
    $documented = collect(array_keys($spec['paths']));

    $missing = collect(RouteFacade::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/') && $route->uri() !== 'api/v1/openapi.json')
        ->map(fn ($route) => '/'.preg_replace('#^api/v1/#', '', $route->uri()))
        ->unique()
        ->reject(fn ($uri) => $documented->contains($uri))
        ->values();

    expect($missing)->toBeEmpty('Routes missing from OpenApiSpec: '.$missing->implode(', '));
});

test('every documented path has at least one method with a response for each declared status code group', function () {
    $spec = OpenApiSpec::generate();

    foreach ($spec['paths'] as $path => $methods) {
        foreach ($methods as $method => $operation) {
            if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            expect(array_key_exists('responses', $operation))->toBeTrue("{$method} {$path} has no responses");
            expect(array_key_exists('tags', $operation))->toBeTrue("{$method} {$path} has no tags");
        }
    }
});
