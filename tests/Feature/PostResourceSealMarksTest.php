<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use App\Models\SealMark;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create([
        'status' => 'published',
        'title' => 'Test Post Title',
        'slug' => 'test-post-title',
    ]);
    $this->comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'status' => 'published',
    ]);
});

test('getPosts includes user_has_recommended and user_has_advise_against for authenticated user', function (): void {
    Sanctum::actingAs($this->user);

    // Create another post to have multiple posts
    $post2 = Post::factory()->create(['status' => 'published']);

    SealMark::create([
        'user_id' => $this->user->id,
        'markable_type' => Post::class,
        'markable_id' => $this->post->id,
        'type' => 'recommended',
        'expires_at' => now()->addDays(30),
    ]);

    SealMark::create([
        'user_id' => $this->user->id,
        'markable_type' => Post::class,
        'markable_id' => $post2->id,
        'type' => 'advise_against',
        'expires_at' => now()->addDays(30),
    ]);

    $response = getJson('/api/v1/posts');

    $response->assertStatus(200);

    $posts = $response->json('data');

    $firstPost = collect($posts)->firstWhere('id', $this->post->id);
    $secondPost = collect($posts)->firstWhere('id', $post2->id);

    expect($firstPost)->not->toBeNull();
    expect($firstPost['user_has_recommended'])->toBeTrue();
    expect($firstPost['user_has_advise_against'])->toBeFalse();

    expect($secondPost)->not->toBeNull();
    expect($secondPost['user_has_recommended'])->toBeFalse();
    expect($secondPost['user_has_advise_against'])->toBeTrue();
});

test('getPost (individual) includes user_has_recommended and user_has_advise_against', function (): void {
    Sanctum::actingAs($this->user);

    SealMark::create([
        'user_id' => $this->user->id,
        'markable_type' => Post::class,
        'markable_id' => $this->post->id,
        'type' => 'recommended',
        'expires_at' => now()->addDays(30),
    ]);

    $response = getJson("/api/v1/posts/permalink/{$this->post->uuid}");

    $response->assertStatus(200);
    $response->assertJson([
        'data' => [
            'user_has_recommended' => true,
            'user_has_advise_against' => false,
        ],
    ]);
});

test('getPosts does not include user_has_* for unauthenticated user', function (): void {
    $response = getJson('/api/v1/posts');

    $response->assertStatus(200);

    $posts = $response->json('data');

    foreach ($posts as $post) {
        expect($post)->not->toHaveKey('user_has_recommended');
        expect($post)->not->toHaveKey('user_has_advise_against');
    }
});

test('getComments includes user_has_recommended and user_has_advise_against', function (): void {
    Sanctum::actingAs($this->user);

    $comment2 = Comment::factory()->create(['post_id' => $this->post->id]);

    SealMark::create([
        'user_id' => $this->user->id,
        'markable_type' => Comment::class,
        'markable_id' => $this->comment->id,
        'type' => 'recommended',
        'expires_at' => now()->addDays(30),
    ]);

    SealMark::create([
        'user_id' => $this->user->id,
        'markable_type' => Comment::class,
        'markable_id' => $comment2->id,
        'type' => 'advise_against',
        'expires_at' => now()->addDays(30),
    ]);

    $response = getJson("/api/v1/posts/{$this->post->id}/comments");

    $response->assertStatus(200);

    $comments = $response->json('data');

    $firstComment = collect($comments)->firstWhere('id', $this->comment->id);
    $secondComment = collect($comments)->firstWhere('id', $comment2->id);

    expect($firstComment)->not->toBeNull();
    expect($firstComment['user_has_recommended'])->toBeTrue();
    expect($firstComment['user_has_advise_against'])->toBeFalse();

    expect($secondComment)->not->toBeNull();
    expect($secondComment['user_has_recommended'])->toBeFalse();
    expect($secondComment['user_has_advise_against'])->toBeTrue();
});

test('expired seal marks do not appear as active', function (): void {
    Sanctum::actingAs($this->user);

    SealMark::create([
        'user_id' => $this->user->id,
        'markable_type' => Post::class,
        'markable_id' => $this->post->id,
        'type' => 'recommended',
        'expires_at' => now()->subDay(), // Expirado
    ]);

    $response = getJson("/api/v1/posts/permalink/{$this->post->uuid}");

    $response->assertStatus(200);
    $response->assertJson([
        'data' => [
            'user_has_recommended' => false,
            'user_has_advise_against' => false,
        ],
    ]);
});

test('user_has_* only shows seals from current user, not other users', function (): void {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($this->user);

    SealMark::create([
        'user_id' => $otherUser->id,
        'markable_type' => Post::class,
        'markable_id' => $this->post->id,
        'type' => 'recommended',
        'expires_at' => now()->addDays(30),
    ]);

    // Update the counter manually (in production SealService does this)
    $this->post->recommended_seals_count = 1;
    $this->post->save();

    $response = getJson("/api/v1/posts/permalink/{$this->post->uuid}");

    $response->assertStatus(200);
    $response->assertJson([
        'data' => [
            'user_has_recommended' => false, // No debe aparecer como true para el usuario actual
            'user_has_advise_against' => false,
            'recommended_seals_count' => 1, // But the counter should reflect the other user's seal
        ],
    ]);
});
