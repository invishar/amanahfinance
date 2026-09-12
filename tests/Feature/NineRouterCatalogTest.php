<?php

use App\Models\LlmSetting;
use App\Models\User;
use App\Services\Ai\NineRouterCatalog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Http::preventStrayRequests();
});

function nineRouterRows(): array
{
    return ['data' => [
        ['id' => 'amina-combo', 'owned_by' => 'combo'],
        ['id' => 'provider-a/chat-model', 'owned_by' => 'provider-a', 'capabilities' => ['tools' => true]],
        ['id' => 'provider-b/no-tools', 'owned_by' => 'provider-b', 'capabilities' => ['tools' => false]],
        ['id' => 'provider-a/chat-model', 'owned_by' => 'provider-a', 'capabilities' => ['tools' => true]],
        ['id' => 'images/painter', 'owned_by' => 'images', 'kind' => 'image'],
        ['id' => ''],
    ]];
}

test('catalog is private to platform admins including against family admins', function () {
    $this->postJson('/api/v1/llm-settings/9router/models', ['base_url' => 'https://router.test'])->assertUnauthorized();
    $this->actingAsFamilyMember('admin');
    $this->postJson('/api/v1/llm-settings/9router/models', ['base_url' => 'https://router.test'])->assertForbidden();
    Http::assertNothingSent();
});

test('catalog discovers combos providers and tools without changing active settings or exposing credentials', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $setting = LlmSetting::factory()->create(['provider' => 'openai_compatible', 'base_url' => 'https://router.test/v1', 'key' => 'stored-router-secret', 'model' => 'old-model']);
    Http::fake(['router.test/v1/models' => Http::response(nineRouterRows())]);

    $response = $this->postJson('/api/v1/llm-settings/9router/models', ['base_url' => 'https://router.test/'])
        ->assertOk()->assertJsonPath('data.base_url', 'https://router.test/v1')
        ->assertJsonCount(3, 'data.models')->assertJsonPath('data.models.0.kind', 'combo')
        ->assertJsonPath('data.models.1.provider', 'provider-a')->assertJsonPath('data.models.1.supports_tools', true)
        ->assertJsonPath('data.models.2.supports_tools', false);
    expect($response->getContent())->not->toContain('stored-router-secret');
    expect($setting->fresh()->model)->toBe('old-model');
    Http::assertSent(fn ($request) => $request->url() === 'https://router.test/v1/models' && $request->hasHeader('Authorization', 'Bearer stored-router-secret'));
});

test('discovery refuses to forward a stored key to a changed endpoint', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    LlmSetting::factory()->create(['provider' => 'openai_compatible', 'base_url' => 'https://original.test/v1', 'key' => 'original-secret']);
    $this->postJson('/api/v1/llm-settings/9router/models', ['base_url' => 'https://other.test/v1'])->assertUnprocessable()->assertJsonValidationErrors('key');
    Http::assertNothingSent();
});

test('new key can preview another endpoint and is not saved or returned', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $setting = LlmSetting::factory()->create(['key' => 'original-secret']);
    Http::fake(['other.test/v1/models' => Http::response(nineRouterRows())]);
    $response = $this->postJson('/api/v1/llm-settings/9router/models', ['base_url' => 'https://other.test', 'key' => 'new-router-secret'])->assertOk();
    expect($response->getContent())->not->toContain('new-router-secret');
    expect($setting->fresh()->key)->toBe('original-secret');
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer new-router-secret'));
});

test('saving a 9router combo persists its exact routing id and compatible protocol', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    Http::fake(['router.test/v1/models' => Http::response(nineRouterRows())]);
    $this->putJson('/api/v1/llm-settings', [
        'gateway' => '9router', 'selection_mode' => 'combo', 'model' => 'amina-combo',
        'base_url' => 'https://router.test', 'key' => 'router-secret-key',
    ])->assertOk()->assertJsonPath('data.gateway', '9router')->assertJsonPath('data.selection_mode', 'combo')
        ->assertJsonPath('data.provider', 'openai_compatible')->assertJsonPath('data.base_url', 'https://router.test/v1');
    expect(LlmSetting::query()->sole()->model)->toBe('amina-combo');
    $this->getJson('/api/v1/llm-settings')->assertJsonPath('data.selection_mode', 'combo')->assertJsonMissingPath('data.key');
});

test('save rejects missing models wrong kind and known unsupported tool calling', function ($model, $mode) {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    Http::fake(['router.test/v1/models' => Http::response(nineRouterRows())]);
    $this->putJson('/api/v1/llm-settings', ['gateway' => '9router', 'selection_mode' => $mode, 'model' => $model, 'base_url' => 'https://router.test', 'key' => 'router-secret-key'])
        ->assertUnprocessable()->assertJsonValidationErrors('model');
    $this->assertDatabaseCount('llm_settings', 0);
})->with([['missing-model', 'model'], ['amina-combo', 'model'], ['provider-a/chat-model', 'combo'], ['provider-b/no-tools', 'model']]);

test('provider model saves and an invalid refresh never overwrites it', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    Http::fake(['router.test/v1/models' => Http::sequence()->push(nineRouterRows())->push(['data' => []])]);
    $input = ['gateway' => '9router', 'selection_mode' => 'model', 'model' => 'provider-a/chat-model', 'base_url' => 'https://router.test/v1', 'key' => 'router-secret-key'];
    $this->putJson('/api/v1/llm-settings', $input)->assertOk()->assertJsonPath('data.model', 'provider-a/chat-model');
    $this->putJson('/api/v1/llm-settings', [...$input, 'model' => 'disappeared'])->assertUnprocessable();
    expect(LlmSetting::query()->sole()->model)->toBe('provider-a/chat-model');
});

test('upstream failure returns a useful error without raw upstream secrets', function ($status, $field) {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    Http::fake(['router.test/v1/models' => Http::response(['error' => 'raw-provider-secret'], $status, ['Location' => 'https://different.test/models'])]);
    $response = $this->postJson('/api/v1/llm-settings/9router/models', ['base_url' => 'https://router.test', 'key' => 'router-secret-key'])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($response->getContent())->not->toContain('raw-provider-secret')->not->toContain('router-secret-key');
    Http::assertSentCount(1);
})->with([[401, 'key'], [403, 'key'], [302, 'base_url'], [429, 'base_url'], [500, 'base_url']]);

test('timeout and malformed catalogs do not change settings', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    Http::fake(['router.test/v1/models' => fn () => throw new ConnectionException('internal secret')]);
    $response = $this->postJson('/api/v1/llm-settings/9router/models', ['base_url' => 'https://router.test', 'key' => 'router-secret-key'])->assertUnprocessable();
    expect($response->getContent())->not->toContain('internal secret');
    Http::fake(['router.test/v1/models' => Http::response(['unexpected' => []])]);
    $this->postJson('/api/v1/llm-settings/9router/models', ['base_url' => 'https://router.test', 'key' => 'router-secret-key'])->assertUnprocessable()->assertJsonValidationErrors('base_url');
    $this->assertDatabaseCount('llm_settings', 0);
});

test('url normalization supports self hosted router and proxy prefixes', function ($input, $expected) {
    expect(NineRouterCatalog::normalizeUrl($input))->toBe($expected);
})->with([
    ['http://localhost:20128', 'http://localhost:20128/v1'],
    ['https://router.test/api/v1/', 'https://router.test/api/v1'],
    ['https://router.test/prefix/v1/models', 'https://router.test/prefix/v1'],
    ['https://router.test/v1/chat/completions', 'https://router.test/v1'],
]);
