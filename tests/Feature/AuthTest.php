<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $response->assertJsonPath('data.user.email', 'budi@example.test');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', ['email' => 'budi@example.test']);
    }

    public function test_register_requires_email_or_phone(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Budi Santoso',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_with_phone_only_is_allowed(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Budi Santoso',
            'phone' => '+6281234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
    }

    public function test_password_returned_is_never_exposed(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Budi Santoso',
            'email' => 'budi2@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $response->assertJsonMissingPath('data.user.password_hash');
    }

    public function test_login_with_correct_credentials_returns_token(): void
    {
        User::factory()->create([
            'email' => 'login@example.test',
            'password_hash' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.test',
            'password' => 'secret123',
        ])->assertOk();

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_with_wrong_password_fails(): void
    {
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
    }

    public function test_login_with_unknown_email_fails(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.test',
            'password' => 'whatever123',
        ])->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_returns_current_user(): void
    {
        $user = User::factory()->create(['full_name' => 'Siti Aminah']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Siti Aminah');
    }

    public function test_logout_revokes_the_token(): void
    {
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
    }
}
