<?php

use App\Models\FamilyMember;
use App\Models\User;

test('login page is reachable for guests', function () {
    $this->get('/login')->assertOk();
});

test('login with valid credentials starts a session and redirects to dashboard', function () {
    $user = User::factory()->create(['email' => 'siti@example.test']);

    $this->post('/login', [
        'email' => 'siti@example.test',
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('login with wrong password keeps the user a guest', function () {
    User::factory()->create(['email' => 'siti@example.test']);

    $this->post('/login', [
        'email' => 'siti@example.test',
        'password' => 'salah-sekali',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('session id is regenerated on login', function () {
    User::factory()->create(['email' => 'siti@example.test']);

    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', [
        'email' => 'siti@example.test',
        'password' => 'password',
    ]);

    expect(session()->getId())->not->toBe($before);
});

test('dashboard is closed to guests and open to a session user', function () {
    $this->get('/dashboard')->assertRedirect('/login');

    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('logout ends the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

test('inertia shares only the family the session user belongs to', function () {
    $member = FamilyMember::factory()->create();
    $outsider = FamilyMember::factory()->create();

    // Sesi menunjuk ke family milik orang lain: harus diabaikan, bukan
    // dipercaya -- aturan #3, family_id tidak pernah datang dari request.
    $props = $this->actingAs($member->user)
        ->withSession(['family_id' => $outsider->family_id])
        ->get('/dashboard')
        ->viewData('page')['props'];

    expect($props['auth']['family']['id'])->toBe($member->family_id);
});

test('inertia shares no user for guests', function () {
    $props = $this->get('/')->viewData('page')['props'];

    expect($props['auth']['user'])->toBeNull();
});
