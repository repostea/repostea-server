<?php

declare(strict_types=1);

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 10,
        'posts_count' => 5,
    ]);
});

test('PostResource includes sub data when present', function (): void {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
        'user_id' => $this->user->id,
        'sub_id' => $this->sub->id,
    ]);

    $post->load('sub', 'user');

    $request = Request::create('/api/posts', 'GET');
    $resource = new PostResource($post);
    $response = $resource->toArray($request);

    expect($response)->toHaveKey('sub');
    expect($response['sub'])->toHaveKeys(['id', 'name', 'display_name', 'icon', 'members_count', 'posts_count']);
    expect($response['sub']['name'])->toBe('test-sub');
    expect($response['sub']['display_name'])->toBe('Test Sub');
});

test('PostResource is_external_import is boolean', function (): void {
    $post = Post::create([
        'title' => 'External Post',
        'content' => 'Test content',
        'user_id' => $this->user->id,
        'source' => 'meneame',
        'source_url' => 'https://www.meneame.net/story/test',
    ]);

    $post->load('user');

    $request = Request::create('/api/posts', 'GET');
    $resource = new PostResource($post);
    $response = $resource->toArray($request);

    expect($response['is_external_import'])->toBeBool();
    expect($response['is_external_import'])->toBeTrue();
});

test('PostResource comments_open is true for recent posts', function (): void {
    config(['posts.commenting_max_age_days' => 30]);

    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'created_at' => now()->subDays(10),
    ]);

    $post->load('user');

    $request = Request::create('/api/posts', 'GET');
    $resource = new PostResource($post);
    $response = $resource->toArray($request);

    expect($response)->toHaveKey('comments_open');
    expect($response['comments_open'])->toBeTrue();
});

test('PostResource comments_open is false for old posts', function (): void {
    config(['posts.commenting_max_age_days' => 30]);

    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'created_at' => now()->subDays(32),
    ]);

    $post->load('user');

    $request = Request::create('/api/posts', 'GET');
    $resource = new PostResource($post);
    $response = $resource->toArray($request);

    expect($response)->toHaveKey('comments_open');
    expect($response['comments_open'])->toBeFalse();
});

test('PostResource comments_open is always true when max age is zero', function (): void {
    config(['posts.commenting_max_age_days' => 0]);

    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'created_at' => now()->subDays(365),
    ]);

    $post->load('user');

    $request = Request::create('/api/posts', 'GET');
    $resource = new PostResource($post);
    $response = $resource->toArray($request);

    expect($response)->toHaveKey('comments_open');
    expect($response['comments_open'])->toBeTrue();
});
