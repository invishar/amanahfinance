<?php

use App\Models\AiProviderError;
use App\Models\Family;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('unauthenticated request is rejected', function () {
    $this->getJson('/api/v1/admin/ai-errors')->assertStatus(401);
});

test('non admin cannot list ai errors', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

    $this->getJson('/api/v1/admin/ai-errors')->assertStatus(403);
});

test('admin can list ai errors across every family', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $family = Family::factory()->create(['name' => 'Keluarga Budi']);
    AiProviderError::factory()->for($family)->create(['status' => 429]);
    AiProviderError::factory()->create(['status' => 413]);

    $response = $this->getJson('/api/v1/admin/ai-errors')->assertOk();

    expect($response->json('meta.total'))->toBe(2);
    expect($response->json('data.0.family_name'))->not->toBeNull();
});

test('admin can filter ai errors by status', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    AiProviderError::factory()->create(['status' => 429]);
    AiProviderError::factory()->create(['status' => 413]);

    $response = $this->getJson('/api/v1/admin/ai-errors?status=429')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.status', 429);
});

test('newest ai error is listed first', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $older = AiProviderError::factory()->create(['created_at' => now()->subHour()]);
    $newer = AiProviderError::factory()->create(['created_at' => now()]);

    $response = $this->getJson('/api/v1/admin/ai-errors')->assertOk();

    $response->assertJsonPath('data.0.id', $newer->id);
    $response->assertJsonPath('data.1.id', $older->id);
});
