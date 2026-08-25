<?php

use App\Models\AiLog;
use App\Models\Family;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('unauthenticated request is rejected', function () {
    $this->getJson('/api/v1/admin/ai-logs')->assertStatus(401);
});

test('non admin cannot list ai logs', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

    $this->getJson('/api/v1/admin/ai-logs')->assertStatus(403);
});

test('admin can list ai logs across every family', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $family = Family::factory()->create(['name' => 'Keluarga Budi']);
    AiLog::factory()->for($family)->create(['user_prompt' => 'abis jajan 20rb']);
    AiLog::factory()->create();

    $response = $this->getJson('/api/v1/admin/ai-logs')->assertOk();

    expect($response->json('meta.total'))->toBe(2);
    expect($response->json('data.0.family_name'))->not->toBeNull();
});

test('admin can filter ai logs by model', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    AiLog::factory()->create(['model' => 'claude-sonnet-4-5']);
    AiLog::factory()->create(['model' => 'openai/gpt-oss-120b']);

    $response = $this->getJson('/api/v1/admin/ai-logs?model=openai/gpt-oss-120b')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.model', 'openai/gpt-oss-120b');
});

test('newest ai log is listed first', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $older = AiLog::factory()->create(['created_at' => now()->subHour()]);
    $newer = AiLog::factory()->create(['created_at' => now()]);

    $response = $this->getJson('/api/v1/admin/ai-logs')->assertOk();

    $response->assertJsonPath('data.0.id', $newer->id);
    $response->assertJsonPath('data.1.id', $older->id);
});
