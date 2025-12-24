<?php

declare(strict_types=1);

use App\Models\KarmaLevel;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

// getLastUpdated tests
test('getLastUpdated returns update timestamps', function (): void {
    $response = getJson('/api/v1/sync/last-updated');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'posts',
        'karma_levels',
    ]);
});

test('getLastUpdated returns null when no data', function (): void {
    Post::query()->delete();
    KarmaLevel::query()->delete();

    $response = getJson('/api/v1/sync/last-updated');

    $response->assertStatus(200);
    $response->assertJsonPath('posts', null);
    $response->assertJsonPath('karma_levels', null);
});

test('getLastUpdated returns latest updated_at from posts', function (): void {
    $oldPost = Post::factory()->create(['updated_at' => now()->subDays(2)]);
    $newPost = Post::factory()->create(['updated_at' => now()]);

    $response = getJson('/api/v1/sync/last-updated');

    $response->assertStatus(200);
    expect($response->json('posts'))->toBe($newPost->updated_at->toDateTimeString());
});

test('getLastUpdated returns latest updated_at from karma_levels', function (): void {
    $oldLevel = KarmaLevel::factory()->create(['updated_at' => now()->subDays(2)]);
    $newLevel = KarmaLevel::factory()->create(['updated_at' => now()]);

    $response = getJson('/api/v1/sync/last-updated');

    $response->assertStatus(200);
    expect($response->json('karma_levels'))->toBe($newLevel->updated_at->toDateTimeString());
});

test('getLastUpdated does not require authentication', function (): void {
    $response = getJson('/api/v1/sync/last-updated');

    $response->assertStatus(200);
});

// syncPosts tests
test('syncPosts requires authentication', function (): void {
    $response = postJson('/api/v1/sync/posts', [
        'last_sync' => now()->subHour()->toISOString(),
    ]);

    $response->assertStatus(401);
});

test('syncPosts requires last_sync', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/sync/posts', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['last_sync']);
});

test('syncPosts validates last_sync is a date', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/sync/posts', [
        'last_sync' => 'not-a-date',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['last_sync']);
});

test('syncPosts returns posts updated after last_sync', function (): void {
    Sanctum::actingAs($this->user);

    $oldPost = Post::factory()->create(['updated_at' => now()->subDays(2)]);
    $newPost1 = Post::factory()->create(['updated_at' => now()->subHour()]);
    $newPost2 = Post::factory()->create(['updated_at' => now()]);

    $response = postJson('/api/v1/sync/posts', [
        'last_sync' => now()->subDay()->toISOString(),
    ]);

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
});

test('syncPosts does not return posts before last_sync', function (): void {
    Sanctum::actingAs($this->user);

    Post::factory()->count(3)->create(['updated_at' => now()->subDays(2)]);

    $response = postJson('/api/v1/sync/posts', [
        'last_sync' => now()->subDay()->toISOString(),
    ]);

    $response->assertStatus(200);
    $response->assertJsonCount(0, 'data');
});

test('syncPosts accepts limit parameter', function (): void {
    Sanctum::actingAs($this->user);

    $lastSync = now()->subDay();
    Post::factory()->count(10)->create(['updated_at' => now()]);

    $response = postJson('/api/v1/sync/posts', [
        'last_sync' => $lastSync->toISOString(),
        'limit' => 5,
    ]);

    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
});

test('syncPosts uses limit 50 by default', function (): void {
    Sanctum::actingAs($this->user);

    $lastSync = now()->subDay();
    Post::factory()->count(60)->create(['updated_at' => now()]);

    $response = postJson('/api/v1/sync/posts', [
        'last_sync' => $lastSync->toISOString(),
    ]);

    $response->assertStatus(200);
    $response->assertJsonCount(50, 'data');
});

test('syncPosts validates minimum limit of 1', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/sync/posts', [
        'last_sync' => now()->subHour()->toISOString(),
        'limit' => 0,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['limit']);
});

test('syncPosts validates maximum limit of 100', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/sync/posts', [
        'last_sync' => now()->subHour()->toISOString(),
        'limit' => 101,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['limit']);
});

test('syncPosts includes user information', function (): void {
    Sanctum::actingAs($this->user);

    Post::factory()->create(['updated_at' => now()]);

    $response = postJson('/api/v1/sync/posts', [
        'last_sync' => now()->subHour()->toISOString(),
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'title', 'user'],
        ],
    ]);
});

test('syncPosts returns respuesta paginada', function (): void {
    Sanctum::actingAs($this->user);

    Post::factory()->count(10)->create(['updated_at' => now()]);

    $response = postJson('/api/v1/sync/posts', [
        'last_sync' => now()->subHour()->toISOString(),
        'limit' => 5,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data',
        'links',
        'meta' => ['current_page', 'per_page', 'total'],
    ]);
});
