<?php

declare(strict_types=1);

/**
 * Smoke tests to ensure API endpoints don't crash with both
 * authenticated and unauthenticated requests.
 *
 * These tests catch errors like missing classes, typos in model names,
 * or code paths that only execute for logged-in users.
 */

use App\Models\Comment;
use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->sub = Sub::create([
        'name' => 'smoke-test-sub-' . uniqid(),
        'display_name' => 'Smoke Test Sub',
        'description' => 'Test sub for smoke tests',
        'created_by' => $this->user->id,
    ]);
    $this->post = Post::factory()->create(['sub_id' => $this->sub->id]);
    $this->comment = Comment::factory()->create(['post_id' => $this->post->id]);
});

dataset('auth_states', [
    'guest' => [false],
    'authenticated' => [true],
]);

test('posts endpoints work for both auth states', function (bool $authenticated): void {
    if ($authenticated) {
        Sanctum::actingAs($this->user);
    }

    getJson('/api/v1/posts')->assertSuccessful();
    getJson("/api/v1/posts/{$this->post->id}")->assertSuccessful();
    getJson("/api/v1/posts/{$this->post->id}/relationships")->assertSuccessful();
})->with('auth_states');

test('comments endpoints work for both auth states', function (bool $authenticated): void {
    if ($authenticated) {
        Sanctum::actingAs($this->user);
    }

    getJson("/api/v1/posts/{$this->post->id}/comments")->assertSuccessful();
})->with('auth_states');

test('subs endpoints work for both auth states', function (bool $authenticated): void {
    if ($authenticated) {
        Sanctum::actingAs($this->user);
    }

    getJson('/api/v1/subs')->assertSuccessful();
    getJson("/api/v1/subs/{$this->sub->name}")->assertSuccessful();
})->with('auth_states');

test('tags endpoints work for both auth states', function (bool $authenticated): void {
    if ($authenticated) {
        Sanctum::actingAs($this->user);
    }

    getJson('/api/v1/tags')->assertSuccessful();
    getJson('/api/v1/tag-categories')->assertSuccessful();
})->with('auth_states');

test('user profile endpoints work for both auth states', function (bool $authenticated): void {
    if ($authenticated) {
        Sanctum::actingAs($this->user);
    }

    getJson("/api/v1/users/by-username/{$this->user->username}")->assertSuccessful();
})->with('auth_states');
