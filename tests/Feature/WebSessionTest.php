<?php

use App\Models\FamilyMember;
use App\Models\User;

/**
 * Sesi cookie untuk klien same-origin (halaman Inertia). Auth-nya sendiri
 * tetap satu pintu di /api/v1/auth/* — tidak ada route web yang login.
 *
 * `Referer` di bawah bukan hiasan: itu yang membuat
 * EnsureFrontendRequestsAreStateful menganggap request datang dari frontend
 * sendiri lalu memasang middleware sesi. Tanpa header itu request tetap
 * stateless, persis seperti klien API lain.
 *
 * Semua assert memakai guard 'web' secara eksplisit: middleware stateful
 * Sanctum mengubah `auth.defaults.guard` jadi 'sanctum' selama request, dan
 * perubahan config itu masih tertinggal saat assert dijalankan.
 */
function fromFrontend(): array
{
    return ['Referer' => config('app.url')];
}

test('api login opens a cookie session for a same-origin request', function () {
    $user = User::factory()->create(['email' => 'siti@example.test']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'siti@example.test',
        'password' => 'password',
    ], fromFrontend())->assertOk();

    $this->assertAuthenticatedAs($user, 'web');
});

test('the api accepts that cookie on its own, with no bearer token', function () {
    $member = FamilyMember::factory()->create();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $member->user->email,
        'password' => 'password',
    ], fromFrontend())->assertOk();

    // Cookie sesi dibawa manual: klien test tidak menyimpan cookie response
    // sendiri, dan tanpa ini request berikutnya cuma lolos karena guard-nya
    // masih menyimpan user dari request sebelumnya — bukan karena cookie.
    $cookies = [];
    foreach ($login->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }
    expect($cookies)->toHaveKey(config('session.cookie'));

    $this->app['auth']->forgetGuards();

    $this->withCookies($cookies)
        ->getJson('/api/v1/families', fromFrontend())
        ->assertOk()
        ->assertJsonPath('data.0.id', $member->family_id);
});

test('api login stays stateless for a client that is not the frontend', function () {
    User::factory()->create(['email' => 'siti@example.test']);

    // Tanpa Referer/Origin yang cocok: tidak ada sesi yang dibuka, dan klien
    // tetap dapat Bearer token seperti sebelumnya.
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'siti@example.test',
        'password' => 'password',
    ])->assertOk();

    expect($response->json('data.token'))->not->toBeEmpty();
    $this->assertGuest('web');
});

test('api logout destroys the cookie session', function () {
    User::factory()->create(['email' => 'siti@example.test']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'siti@example.test',
        'password' => 'password',
    ], fromFrontend())->assertOk();

    $this->postJson('/api/v1/auth/logout', [], fromFrontend())->assertNoContent();

    $this->assertGuest('web');
});

test('api register opens a session too, so onboarding is reachable right away', function () {
    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Rizki Pratama',
        'email' => 'rizki@example.test',
        'password' => 'password123',
    ], fromFrontend())->assertCreated();

    $this->assertAuthenticated('web');
    $this->get('/onboarding')->assertOk();
});

test('a token client can still log out without a session', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});
