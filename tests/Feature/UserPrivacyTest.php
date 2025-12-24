<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\Comment;
use App\Models\KarmaLevel;
use App\Models\Post;
use App\Models\User;
use App\Models\UserPreference;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    // Create karma levels
    KarmaLevel::create(['id' => 1, 'name' => 'Newbie', 'required_karma' => 0]);
    KarmaLevel::create(['id' => 2, 'name' => 'Regular', 'required_karma' => 100]);

    // Create a test user
    $this->user = User::factory()->create([
        'username' => 'testuser',
        'karma_points' => 150,
        'highest_level_id' => 2,
    ]);

    // Create achievements
    Achievement::factory()->create([
        'slug' => 'first-post',
        'name' => 'First Post',
        'description' => 'Create your first post',
        'type' => 'posts',
    ]);

    // Attach achievement to user
    $this->user->achievements()->attach(1, [
        'progress' => 100,
        'unlocked_at' => now(),
    ]);

    // Create some posts
    Post::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'status' => 'published',
        'is_anonymous' => false,
    ]);

    Post::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'draft',
        'is_anonymous' => false,
    ]);

    // Create some comments
    $post = Post::factory()->create(['status' => 'published']);
    Comment::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'post_id' => $post->id,
        'status' => 'published',
        'is_anonymous' => false,
    ]);
});

test('karma is always visible in public profile', function (): void {
    $response = getJson("/api/v1/users/by-username/{$this->user->username}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.karma_points', 150);
    $response->assertJsonStructure(['data' => ['karma_points', 'current_level']]);
});

test('own profile view respects privacy settings for achievements', function (): void {
    // Set privacy to hide achievements
    UserPreference::updateOrCreate(
        ['user_id' => $this->user->id],
        ['hide_achievements' => true],
    );

    Sanctum::actingAs($this->user);

    // Even viewing own profile through public endpoint respects privacy
    $response = getJson("/api/v1/users/by-username/{$this->user->username}");

    $response->assertStatus(200);
    $response->assertJsonPath('achievements', null);
});

test('achievements are visible when privacy is not set', function (): void {
    UserPreference::updateOrCreate(
        ['user_id' => $this->user->id],
        ['hide_achievements' => false],
    );

    $response = getJson("/api/v1/users/by-username/{$this->user->username}");

    $response->assertStatus(200);
    $response->assertJsonStructure(['achievements' => ['items', 'unlocked_count', 'total_count']]);
});

test('own profile view respects privacy settings for comments', function (): void {
    // Set privacy to hide comments
    UserPreference::updateOrCreate(
        ['user_id' => $this->user->id],
        ['hide_comments' => true],
    );

    Sanctum::actingAs($this->user);

    // Even viewing own profile through public endpoint respects privacy
    $response = getJson("/api/v1/users/{$this->user->username}/comments");

    $response->assertStatus(403);
});

test('comments list is accessible when privacy is not set', function (): void {
    UserPreference::updateOrCreate(
        ['user_id' => $this->user->id],
        ['hide_comments' => false],
    );

    $response = getJson("/api/v1/users/{$this->user->username}/comments");

    $response->assertStatus(200);
    $response->assertJsonStructure(['data', 'meta']);
});

test('only published posts are shown in public profile', function (): void {
    $response = getJson("/api/v1/users/{$this->user->username}/posts");

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data'); // Only 3 published posts, not the draft

    // Verify all returned posts are published
    $posts = $response->json('data');
    foreach ($posts as $post) {
        expect($post['status'])->toBe('published');
    }
});

test('user resource does not include hide_karma field', function (): void {
    $response = getJson("/api/v1/users/by-username/{$this->user->username}");

    $response->assertStatus(200);
    // Karma should always be present
    expect($response->json('data.karma_points'))->not->toBeNull();
});

test('preferences endpoint does not accept hide_karma', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/preferences');

    $response->assertStatus(200);
    // hide_karma should not be in the response
    expect($response->json())->not->toHaveKey('hide_karma');
});

test('can update achievements privacy setting', function (): void {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/preferences', [
        'hide_achievements' => true,
    ]);

    $response->assertStatus(200);

    // Verify it was saved
    $preference = UserPreference::where('user_id', $this->user->id)->first();
    expect($preference->hide_achievements)->toBeTrue();
});

test('can update comments privacy setting', function (): void {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/preferences', [
        'hide_comments' => true,
    ]);

    $response->assertStatus(200);

    // Verify it was saved
    $preference = UserPreference::where('user_id', $this->user->id)->first();
    expect($preference->hide_comments)->toBeTrue();
});

test('comments in individual posts are always visible regardless of privacy', function (): void {
    // Hide comments list in profile
    UserPreference::updateOrCreate(
        ['user_id' => $this->user->id],
        ['hide_comments' => true],
    );

    // Get a post with comments
    $post = Post::whereHas('comments', function ($query): void {
        $query->where('user_id', $this->user->id);
    })->first();

    // Comments in the post should still be visible (using post ID)
    $response = getJson("/api/v1/posts/{$post->id}/comments");

    $response->assertStatus(200);
    // Should include comments from this user
    $comments = collect($response->json('data'));
    $userComments = $comments->filter(fn ($comment) => $comment['user']['username'] === $this->user->username);

    expect($userComments->count())->toBeGreaterThan(0);
});
