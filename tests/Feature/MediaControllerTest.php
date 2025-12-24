<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

test('validateMediaUrl does not require authentication', function (): void {
    $response = postJson('/api/v1/media/validate', [
        'url' => 'https://example.com/video.mp4',
    ]);

    $response->assertStatus(200);
});

test('validateMediaUrl requires url', function (): void {
    $response = postJson('/api/v1/media/validate', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['url']);
});

test('validateMediaUrl validates url format', function (): void {
    $response = postJson('/api/v1/media/validate', [
        'url' => 'not-a-valid-url',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['url']);
});

test('validateMediaUrl accepts valid http urls', function (): void {
    $response = postJson('/api/v1/media/validate', [
        'url' => 'http://example.com/video.mp4',
    ]);

    $response->assertStatus(200);
});

test('validateMediaUrl accepts valid https urls', function (): void {
    $response = postJson('/api/v1/media/validate', [
        'url' => 'https://example.com/video.mp4',
    ]);

    $response->assertStatus(200);
});

test('validateMediaUrl accepts youtube urls', function (): void {
    $response = postJson('/api/v1/media/validate', [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $response->assertStatus(200);
});

test('validateMediaUrl accepts vimeo urls', function (): void {
    $response = postJson('/api/v1/media/validate', [
        'url' => 'https://vimeo.com/123456789',
    ]);

    $response->assertStatus(200);
});

test('validateMediaUrl returns json response', function (): void {
    $response = postJson('/api/v1/media/validate', [
        'url' => 'https://example.com/video.mp4',
    ]);

    $response->assertStatus(200);
    $response->assertJson([]);
});

test('getMediaInfo does not require authentication', function (): void {
    $response = postJson('/api/v1/media/info', [
        'url' => 'https://example.com/video.mp4',
    ]);

    $response->assertStatus(200);
});

test('getMediaInfo requires url', function (): void {
    $response = postJson('/api/v1/media/info', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['url']);
});

test('getMediaInfo validates url format', function (): void {
    $response = postJson('/api/v1/media/info', [
        'url' => 'not-a-valid-url',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['url']);
});

test('getMediaInfo accepts valid http urls', function (): void {
    $response = postJson('/api/v1/media/info', [
        'url' => 'http://example.com/video.mp4',
    ]);

    $response->assertStatus(200);
});

test('getMediaInfo accepts valid https urls', function (): void {
    $response = postJson('/api/v1/media/info', [
        'url' => 'https://example.com/video.mp4',
    ]);

    $response->assertStatus(200);
});

test('getMediaInfo accepts youtube urls', function (): void {
    $response = postJson('/api/v1/media/info', [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $response->assertStatus(200);
});

test('getMediaInfo accepts vimeo urls', function (): void {
    $response = postJson('/api/v1/media/info', [
        'url' => 'https://vimeo.com/123456789',
    ]);

    $response->assertStatus(200);
});

test('getMediaInfo returns json response', function (): void {
    $response = postJson('/api/v1/media/info', [
        'url' => 'https://example.com/video.mp4',
    ]);

    $response->assertStatus(200);
    $response->assertJson([]);
});
