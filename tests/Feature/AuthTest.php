<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('register creates user and returns token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Budi Santoso',
        'email' => 'budi@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $response->assertJsonPath('data.user.email', 'budi@example.test');
    $this->assertNotEmpty($response->json('data.token'));
    $this->assertDatabaseHas('users', ['email' => 'budi@example.test']);
});

test('register requires email or phone', function () {
    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Budi Santoso',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('register with phone only is allowed', function () {
    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Budi Santoso',
        'phone' => '+6281234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();
});

test('register succeeds without password_confirmation', function () {
    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Budi Santoso',
        'email' => 'budi.no-confirm@example.test',
        'password' => 'password123',
    ])->assertCreated();

    $this->assertDatabaseHas('users', ['email' => 'budi.no-confirm@example.test']);
});

test('register still rejects a mismatched password_confirmation when sent', function () {
    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Budi Santoso',
        'email' => 'budi.mismatch@example.test',
        'password' => 'password123',
        'password_confirmation' => 'not-the-same',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    $this->assertDatabaseMissing('users', ['email' => 'budi.mismatch@example.test']);
});

test('password returned is never exposed', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Budi Santoso',
        'email' => 'budi2@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $response->assertJsonMissingPath('data.user.password_hash');
});

test('login with correct credentials returns token', function () {
    User::factory()->create([
        'email' => 'login@example.test',
        'password_hash' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.test',
        'password' => 'secret123',
    ])->assertOk();

    $this->assertNotEmpty($response->json('data.token'));
});

test('login with wrong password fails', function () {
    User::factory()->create([
        'email' => 'login2@example.test',
        'password_hash' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'login2@example.test',
        'password' => 'wrong-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login with unknown email fails', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.test',
        'password' => 'whatever123',
    ])->assertStatus(422);
});

test('me requires authentication', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

test('me returns current user', function () {
    $user = User::factory()->create(['full_name' => 'Siti Aminah']);
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Siti Aminah');
});

test('logout revokes the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    // Sanctum's RequestGuard caches the resolved user for as long as the
    // app container lives, which -- unlike real requests -- spans every
    // simulated call within one test. Auth::forgetGuards() is Laravel's
    // own escape hatch for exactly this.
    auth()->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});
