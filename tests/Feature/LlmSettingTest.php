<?php

use App\Models\LlmSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

test('unauthenticated request is rejected', function () {
    $this->getJson('/api/v1/llm-settings')->assertStatus(401);
});

test('non platform admin cannot view settings', function () {
    Sanctum::actingAs(User::factory()->create(['is_platform_admin' => false]));

    $this->getJson('/api/v1/llm-settings')->assertStatus(403);
});

test('non platform admin cannot update settings', function () {
    Sanctum::actingAs(User::factory()->create(['is_platform_admin' => false]));

    $this->putJson('/api/v1/llm-settings', ['model' => 'claude-opus-5'])
        ->assertStatus(403);
});

test('platform admin sees env fallback when no row exists yet, key never in response', function () {
    Sanctum::actingAs(User::factory()->create(['is_platform_admin' => true]));

    $this->getJson('/api/v1/llm-settings')
        ->assertOk()
        ->assertJsonPath('data.model', config('services.llm.model'))
        ->assertJsonMissingPath('data.key');

    $this->assertDatabaseCount('llm_settings', 0);
});

test('platform admin can create the settings row via update', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    Sanctum::actingAs($admin);

    $response = $this->putJson('/api/v1/llm-settings', [
        'key' => 'sk-ant-test-secret-value',
        'model' => 'claude-opus-5',
        'base_url' => 'https://example.test',
    ])->assertOk();

    $response->assertJsonPath('data.model', 'claude-opus-5');
    $response->assertJsonPath('data.has_key', true);
    $response->assertJsonPath('data.key_preview', '...alue');
    $response->assertJsonMissingPath('data.key');
    $response->assertJsonPath('data.updated_by', $admin->id);

    $this->assertDatabaseCount('llm_settings', 1);

    // Encrypted at rest: the raw DB column must not contain the plaintext key.
    $raw = DB::table('llm_settings')->value('key');
    expect($raw)->not->toContain('sk-ant-test-secret-value');

    // But decrypts correctly through the model.
    expect(LlmSetting::query()->first()->key)->toBe('sk-ant-test-secret-value');
});

test('updating without key preserves the existing key', function () {
    Sanctum::actingAs(User::factory()->create(['is_platform_admin' => true]));

    $this->putJson('/api/v1/llm-settings', [
        'key' => 'sk-ant-original-key',
        'model' => 'claude-sonnet-5',
    ])->assertOk();

    $this->putJson('/api/v1/llm-settings', [
        'model' => 'claude-opus-5',
    ])
        ->assertOk()
        ->assertJsonPath('data.model', 'claude-opus-5')
        ->assertJsonPath('data.has_key', true);

    expect(LlmSetting::query()->first()->key)->toBe('sk-ant-original-key');
});

test('model is required on update', function () {
    Sanctum::actingAs(User::factory()->create(['is_platform_admin' => true]));

    $this->putJson('/api/v1/llm-settings', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['model']);
});
