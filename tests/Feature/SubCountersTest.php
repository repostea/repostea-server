<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create(['karma_points' => 5000, 'highest_level_id' => 4]);
    $this->sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 0,
        'posts_count' => 0,
    ]);
});

test('posts_count increments when a post is created in sub', function (): void {
    expect($this->sub->posts_count)->toBe(0);

    Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
        'user_id' => $this->user->id,
        'sub_id' => $this->sub->id,
        'status' => 'published',
    ]);

    $this->sub->refresh();
    expect($this->sub->posts_count)->toBe(1);
});

test('posts_count decrements when a post is deleted from sub', function (): void {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content',
        'user_id' => $this->user->id,
        'sub_id' => $this->sub->id,
        'status' => 'published',
    ]);

    $this->sub->refresh();
    expect($this->sub->posts_count)->toBe(1);

    $post->delete();

    $this->sub->refresh();
    expect($this->sub->posts_count)->toBe(0);
});

test('counter reflects reality when creating and deleting posts', function (): void {
    // Create 3 posts
    for ($i = 0; $i < 3; $i++) {
        Post::create([
            'title' => "Test Post {$i}",
            'content' => 'Test content',
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => 'published',
        ]);
    }

    $this->sub->refresh();
    expect($this->sub->posts_count)->toBe(3);

    // Delete one post
    Post::where('sub_id', $this->sub->id)->first()->delete();

    $this->sub->refresh();
    expect($this->sub->posts_count)->toBe(2);
});

test('members_count increments when a user joins sub', function (): void {
    $newUser = User::factory()->create();
    Sanctum::actingAs($newUser);

    expect($this->sub->members_count)->toBe(0);

    postJson("/api/v1/subs/{$this->sub->id}/join");

    $this->sub->refresh();
    expect($this->sub->members_count)->toBe(1);
});

test('members_count decrements when a user leaves sub', function (): void {
    $newUser = User::factory()->create();
    $this->sub->subscribers()->attach($newUser->id);
    $this->sub->update(['members_count' => 1]);

    expect($this->sub->members_count)->toBe(1);

    Sanctum::actingAs($newUser);
    postJson("/api/v1/subs/{$this->sub->id}/leave");

    $this->sub->refresh();
    expect($this->sub->members_count)->toBe(0);
});

test('counter is accurate with multiple posts', function (): void {
    for ($i = 0; $i < 5; $i++) {
        Post::create([
            'title' => "Test Post {$i}",
            'content' => 'Test content',
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => 'published',
        ]);
    }

    $this->sub->refresh();
    expect($this->sub->posts_count)->toBe(5);
});
