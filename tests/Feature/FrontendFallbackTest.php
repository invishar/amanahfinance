<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(public_path('__test-fallback.html'));
    File::deleteDirectory(public_path('__test-fallback-dir'));
});

test('fallback serves a flat html file for a clean url', function () {
    File::put(public_path('__test-fallback.html'), '<h1>flat</h1>');

    $this->get('/__test-fallback')
        ->assertStatus(200)
        ->assertSee('flat', false);
});

test('fallback serves dir/index.html for a clean url', function () {
    File::makeDirectory(public_path('__test-fallback-dir'));
    File::put(public_path('__test-fallback-dir/index.html'), '<h1>dir</h1>');

    $this->get('/__test-fallback-dir')
        ->assertStatus(200)
        ->assertSee('dir', false);
});

test('fallback does not intercept unknown api paths', function () {
    $this->getJson('/api/v1/this-route-does-not-exist')
        ->assertStatus(404);
});

test('fallback returns 404 when no matching static file exists', function () {
    $this->get('/this-page-does-not-exist-anywhere')
        ->assertStatus(404);
});
