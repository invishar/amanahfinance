<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('member can upload an image and gets back a url', function () {
    Storage::fake('public');
    [, $family] = $this->actingAsFamilyMember('member');

    $response = $this->postJson('/api/v1/uploads', [
        'file' => UploadedFile::fake()->image('struk.jpg'),
    ])->assertCreated();

    $response->assertJsonStructure(['data' => ['url', 'mime', 'size']]);
    expect($response->json('data.url'))->toContain($family->id);

    $path = parse_url($response->json('data.url'), PHP_URL_PATH);
    $relative = preg_replace('#^/storage/#', '', $path);
    Storage::disk('public')->assertExists($relative);
});

test('member can upload audio', function () {
    Storage::fake('public');
    $this->actingAsFamilyMember('member');

    $this->postJson('/api/v1/uploads', [
        'file' => UploadedFile::fake()->create('memo.mp3', 100, 'audio/mpeg'),
    ])->assertCreated()
        ->assertJsonPath('data.mime', 'audio/mpeg');
});

test('viewer cannot upload', function () {
    Storage::fake('public');
    $this->actingAsFamilyMember('viewer');

    $this->postJson('/api/v1/uploads', [
        'file' => UploadedFile::fake()->image('struk.jpg'),
    ])->assertStatus(403);
});

test('rejects disallowed mime type', function () {
    Storage::fake('public');
    $this->actingAsFamilyMember('member');

    $this->postJson('/api/v1/uploads', [
        'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('rejects file over the configured size limit', function () {
    Storage::fake('public');
    $this->actingAsFamilyMember('member');
    config(['amina.uploads.max_kb' => 100]);

    $this->postJson('/api/v1/uploads', [
        'file' => UploadedFile::fake()->create('struk.jpg', 200, 'image/jpeg'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});
