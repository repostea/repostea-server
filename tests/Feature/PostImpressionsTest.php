<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    Cache::flush();
});

test('registerImpressions increments impressions for valid posts', function (): void {
    $posts = Post::factory()->count(3)->create(['status' => 'published']);
    $postIds = $posts->pluck('id')->toArray();

    $response = postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode($postIds),
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'registered' => 3,
    ]);

    // Verify impressions were incremented
    foreach ($posts as $post) {
        $post->refresh();
        expect($post->impressions)->toBe(1);
    }
});

test('registerImpressions returns error when no post_ids provided', function (): void {
    $response = postJson('/api/v1/posts/impressions', []);

    $response->assertStatus(400);
    $response->assertJson([
        'success' => false,
        'message' => 'No post IDs provided.',
    ]);
});

test('registerImpressions returns error for invalid JSON', function (): void {
    $response = postJson('/api/v1/posts/impressions', [
        'post_ids' => 'invalid-json',
    ]);

    $response->assertStatus(400);
    $response->assertJson([
        'success' => false,
        'message' => 'Invalid post IDs format.',
    ]);
});

test('registerImpressions deduplicates impressions per session', function (): void {
    $post = Post::factory()->create(['status' => 'published', 'impressions' => 0]);

    // First impression
    $response = postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode([$post->id]),
    ]);

    $response->assertStatus(200);
    $response->assertJson(['registered' => 1]);

    $post->refresh();
    expect($post->impressions)->toBe(1);

    // Second impression within 24h should be deduplicated
    $response = postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode([$post->id]),
    ]);

    $response->assertStatus(200);
    $response->assertJson(['registered' => 0]);

    $post->refresh();
    expect($post->impressions)->toBe(1); // Still 1, not 2
});

test('registerImpressions limits batch size to 50', function (): void {
    $posts = Post::factory()->count(60)->create(['status' => 'published']);
    $postIds = $posts->pluck('id')->toArray();

    $response = postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode($postIds),
    ]);

    $response->assertStatus(200);
    // Only first 50 should be processed
    expect($response->json('registered'))->toBeLessThanOrEqual(50);
});

test('registerImpressions filters out invalid IDs', function (): void {
    $post = Post::factory()->create(['status' => 'published']);

    $response = postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode([$post->id, -1, 0, 'invalid', null]),
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'registered' => 1,
    ]);
});

test('registerImpressions respects rate limiting', function (): void {
    $posts = Post::factory()->count(10)->create(['status' => 'published']);

    // Simulate hitting rate limit by setting cache
    Cache::put('impressions_rate_' . request()->ip(), 100, now()->addMinute());

    $postIds = $posts->pluck('id')->toArray();
    $response = postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode($postIds),
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'registered' => 0,
    ]);
});

test('registerImpressions works for authenticated users', function (): void {
    $this->actingAs($this->user);

    $post = Post::factory()->create(['status' => 'published', 'impressions' => 0]);

    $response = postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode([$post->id]),
    ]);

    $response->assertStatus(200);
    $response->assertJson(['registered' => 1]);

    $post->refresh();
    expect($post->impressions)->toBe(1);
});

test('registerImpressions deduplicates by user ID for authenticated users', function (): void {
    $this->actingAs($this->user);

    $post = Post::factory()->create(['status' => 'published', 'impressions' => 0]);

    // First impression
    postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode([$post->id]),
    ]);

    $post->refresh();
    expect($post->impressions)->toBe(1);

    // Second impression should be deduplicated by user ID
    postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode([$post->id]),
    ]);

    $post->refresh();
    expect($post->impressions)->toBe(1); // Still 1
});

test('registerImpressions handles empty array', function (): void {
    $response = postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode([]),
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'registered' => 0,
    ]);
});

test('PostResource includes impressions field', function (): void {
    $post = Post::factory()->create([
        'status' => 'published',
        'impressions' => 42,
    ]);

    $response = $this->getJson("/api/v1/posts/slug/{$post->slug}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.impressions', 42);
});

test('registerView also counts as impression for direct access', function (): void {
    $post = Post::factory()->create([
        'status' => 'published',
        'views' => 0,
        'impressions' => 0,
    ]);

    // Register a view (simulating direct access to post)
    $response = postJson("/api/v1/posts/{$post->id}/view");

    $response->assertStatus(200);

    $post->refresh();
    expect($post->views)->toBe(1);
    expect($post->impressions)->toBe(1); // Should also count as impression
});

test('registerView does not duplicate impression if already counted', function (): void {
    $this->actingAs($this->user);

    $post = Post::factory()->create([
        'status' => 'published',
        'views' => 0,
        'impressions' => 0,
    ]);

    // First, register impression via batch endpoint (simulating listing view)
    postJson('/api/v1/posts/impressions', [
        'post_ids' => json_encode([$post->id]),
    ]);

    $post->refresh();
    expect($post->impressions)->toBe(1);

    // Clear view cache to allow view registration
    Cache::forget('post_view_' . $post->id . '_user_' . $this->user->id);

    // Now register a view (direct access after seeing in listing)
    postJson("/api/v1/posts/{$post->id}/view");

    $post->refresh();
    expect($post->views)->toBe(1);
    expect($post->impressions)->toBe(1); // Should still be 1, not 2
});
