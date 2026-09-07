<?php

use App\Models\FamilyMember;
use App\Models\User;

/** Halaman aplikasi: semuanya butuh sesi, tidak ada yang publik. */
$appPages = [
    '/chat' => 'App/Chat',
    '/dashboard' => 'App/Dashboard',
    '/transactions' => 'App/Transactions',
    '/wallets' => 'App/Wallets',
    '/accounts' => 'App/Accounts',
    '/income' => 'App/Income',
    '/goals' => 'App/Goals',
    '/analysis' => 'App/Analysis',
    '/settings' => 'App/Settings',
    '/onboarding' => 'Auth/Onboarding',
];

$adminPages = [
    '/admin' => 'Admin/Dashboard',
    '/admin/users' => 'Admin/Users',
    '/admin/payments' => 'Admin/Payments',
    '/admin/llm-settings' => 'Admin/LlmSettings',
    '/admin/ai-errors' => 'Admin/AiErrors',
    '/admin/ai-logs' => 'Admin/AiLogs',
];

$guestPages = [
    '/' => 'Home',
    '/login' => 'Auth/Login',
    '/register' => 'Auth/Register',
    '/admin/login' => 'Admin/Login',
];

/** Dataset asosiatif hanya mengirim value-nya; pasangan path+komponen harus
 *  dibungkus jadi list dua elemen supaya keduanya sampai ke test. */
$pairs = fn (array $map) => array_map(null, array_keys($map), array_values($map));

test('guest pages render their component', function (string $path, string $component) {
    expect($this->get($path)->assertOk()->viewData('page')['component'])->toBe($component);
})->with($pairs($guestPages));

test('app pages render for a session user', function (string $path, string $component) {
    $user = User::factory()->create();

    expect($this->actingAs($user)->get($path)->assertOk()->viewData('page')['component'])
        ->toBe($component);
})->with($pairs($appPages));

test('app pages are closed to guests', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(array_keys($appPages));

test('admin pages render for an admin', function (string $path, string $component) {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    expect($this->actingAs($admin)->get($path)->assertOk()->viewData('page')['component'])
        ->toBe($component);
})->with($pairs($adminPages));

test('admin pages are closed to a non-admin session user', function (string $path) {
    $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
})->with(array_keys($adminPages));

test('admin pages are closed to guests', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(array_keys($adminPages));

test('the session that guards a page also authenticates the api', function () {
    $member = FamilyMember::factory()->create();

    // Halaman ditutup middleware `auth`, dan sesi yang sama itu pula yang
    // dipakai klien untuk menembak /api/v1 -- tanpa Bearer token sama sekali.
    $this->actingAs($member->user)->get('/dashboard')->assertOk();

    $this->actingAs($member->user)
        ->getJson('/api/v1/families')
        ->assertOk()
        ->assertJsonPath('data.0.id', $member->family_id);
});
