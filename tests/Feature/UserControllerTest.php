<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\Comment;
use App\Models\KarmaLevel;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'username' => 'testuser',
        'karma_points' => 500,
        'created_at' => now()->subDays(40),
    ]);

    // Create karma levels for tests
    KarmaLevel::create([
        'id' => 1,
        'name' => 'Newbie',
        'required_karma' => 0,
    ]);

    KarmaLevel::create([
        'id' => 2,
        'name' => 'Regular',
        'required_karma' => 100,
    ]);

    KarmaLevel::create([
        'id' => 3,
        'name' => 'Veteran',
        'required_karma' => 1000,
    ]);
});

test('profile returns authenticated user profile', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/profile');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'user' => ['id', 'username'],
        'karma_multiplier',
        'achievements',
    ]);
    $response->assertJsonPath('user.username', 'testuser');
});

test('profile requires authentication', function (): void {
    $response = getJson('/api/v1/profile');

    $response->assertStatus(401);
});

test('profile returns complete profile with grouped achievements', function (): void {
    Sanctum::actingAs($this->user);

    // Create achievements with valid types
    Achievement::create([
        'slug' => 'first-post',
        'name' => 'First Post',
        'description' => 'Create your first post',
        'icon' => '📝',
        'type' => 'post',
        'karma_bonus' => 10,
        'requirements' => json_encode(['posts_count' => 1]),
    ]);

    Achievement::create([
        'slug' => 'first-comment',
        'name' => 'First Comment',
        'description' => 'Create your first comment',
        'icon' => '💬',
        'type' => 'comment',
        'karma_bonus' => 5,
        'requirements' => json_encode(['comments_count' => 1]),
    ]);

    $response = getJson('/api/v1/profile');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'user',
        'streak',
        'karma_multiplier',
        'achievements' => [
            'items',
            'unlocked_count',
            'total_count',
        ],
    ]);
    expect($response->json('achievements.total_count'))->toBeGreaterThanOrEqual(2);
});

test('getByUsername returns user by username', function (): void {
    $response = getJson("/api/v1/users/by-username/{$this->user->username}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.username', 'testuser');
    $response->assertJsonStructure([
        'data',
        'achievements',
    ]);
});

test('getByUsername returns 404 if user does not exist', function (): void {
    $response = getJson('/api/v1/users/by-username/nonexistent');

    $response->assertStatus(404);
    $response->assertJsonPath('message', 'User not found.');
});

test('getByUsername returns 404 for deleted user', function (): void {
    $this->user->update(['deleted_at' => now()]);

    $response = getJson("/api/v1/users/by-username/{$this->user->username}");

    $response->assertStatus(404);
    $response->assertJsonPath('message', 'This user account has been deleted.');
});

test('getUserPosts returns user posts', function (): void {
    Post::factory()->count(5)->create([
        'user_id' => $this->user->id,
        'is_anonymous' => false,
    ]);

    $response = getJson("/api/v1/users/{$this->user->username}/posts");

    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
});

test('getUserPosts excludes anonymous posts', function (): void {
    Post::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'is_anonymous' => false,
    ]);

    Post::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'is_anonymous' => true,
    ]);

    $response = getJson("/api/v1/users/{$this->user->username}/posts");

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
});

test('getUserPosts returns 404 for deleted user', function (): void {
    $this->user->update(['deleted_at' => now()]);

    $response = getJson("/api/v1/users/{$this->user->username}/posts");

    $response->assertStatus(404);
});

test('getUserPosts respects pagination', function (): void {
    Post::factory()->count(20)->create([
        'user_id' => $this->user->id,
        'is_anonymous' => false,
    ]);

    $response = getJson("/api/v1/users/{$this->user->username}/posts?per_page=5");

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(5);
});

test('getUserComments returns user comments', function (): void {
    $post = Post::factory()->create();

    Comment::factory()->count(5)->create([
        'user_id' => $this->user->id,
        'post_id' => $post->id,
        'is_anonymous' => false,
    ]);

    $response = getJson("/api/v1/users/{$this->user->username}/comments");

    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
});

test('getUserComments excludes anonymous comments', function (): void {
    $post = Post::factory()->create();

    Comment::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'post_id' => $post->id,
        'is_anonymous' => false,
    ]);

    Comment::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'post_id' => $post->id,
        'is_anonymous' => true,
    ]);

    $response = getJson("/api/v1/users/{$this->user->username}/comments");

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
});

test('updateProfile updates user information', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/profile', [
        'username' => 'newtestuser',
        'bio' => 'New bio',
        'professional_title' => 'Developer',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('user.bio', 'New bio');

    $this->user->refresh();
    expect($this->user->bio)->toBe('New bio');
    expect($this->user->professional_title)->toBe('Developer');
});

test('updateProfile does not allow changing email', function (): void {
    Sanctum::actingAs($this->user);

    $oldEmail = $this->user->email;

    $response = putJson('/api/v1/profile', [
        'email' => 'newemail@example.com',
    ]);

    $response->assertStatus(422);

    $this->user->refresh();
    expect($this->user->email)->toBe($oldEmail);
});

test('updateProfile allows cambiar username', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/profile', [
        'username' => 'updatedusername',
    ]);

    $response->assertStatus(200);

    $this->user->refresh();
    expect($this->user->username)->toBe('updatedusername');
});

test('moderationHistory returns moderation history', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/profile/moderation-history');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'bans' => ['active', 'history'],
        'strikes' => ['active', 'history'],
        'moderated_posts',
        'moderated_comments',
    ]);
});

test('searchUsers searches users by username', function (): void {
    User::factory()->create(['username' => 'johndoe', 'is_guest' => false]);
    User::factory()->create(['username' => 'janedoe', 'is_guest' => false]);
    User::factory()->create(['username' => 'alice', 'is_guest' => false]);

    $response = getJson('/api/v1/users/search?q=doe');

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
});

test('searchUsers requires minimum 2 characters', function (): void {
    $response = getJson('/api/v1/users/search?q=a');

    $response->assertStatus(200);
    $response->assertJsonCount(0, 'data');
});

test('searchUsers excludes guest users', function (): void {
    User::factory()->create(['username' => 'guestuser', 'is_guest' => true]);
    User::factory()->create(['username' => 'realuser', 'is_guest' => false]);

    $response = getJson('/api/v1/users/search?q=user');

    $response->assertStatus(200);
    $data = $response->json('data');
    $usernames = collect($data)->pluck('username')->toArray();
    expect($usernames)->not->toContain('guestuser');
});

test('searchUsers limita resultados a 10', function (): void {
    for ($i = 0; $i < 15; $i++) {
        User::factory()->create([
            'username' => "testuser{$i}",
            'is_guest' => false,
        ]);
    }

    $response = getJson('/api/v1/users/search?q=testuser');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBeLessThanOrEqual(10);
});

test('deleteAccount deletes user account', function (): void {
    $this->user->update(['password' => bcrypt('password123')]);
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/profile', [
        'password' => 'password123',
        '_method' => 'DELETE',
    ]);

    $response->assertStatus(200);

    $this->user->refresh();
    expect($this->user->is_deleted)->toBeTrue();
    expect($this->user->deleted_at)->not->toBeNull();
});

test('deleteAccount requires correct password', function (): void {
    $this->user->update(['password' => bcrypt('password123')]);
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/profile', [
        'password' => 'wrongpassword',
        '_method' => 'DELETE',
    ]);

    $response->assertStatus(422);
});

test('deleteAccount anonymizes user data', function (): void {
    $this->user->update([
        'password' => bcrypt('password123'),
        'email' => 'test@example.com',
        'bio' => 'My bio',
        'display_name' => 'Test User',
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/profile', [
        'password' => 'password123',
        '_method' => 'DELETE',
    ]);

    $response->assertStatus(200);

    $this->user->refresh();
    expect($this->user->bio)->toBeNull();
    expect($this->user->email)->toContain('deleted');
    expect($this->user->is_deleted)->toBeTrue();
});

test('deleteAccount revokes all tokens', function (): void {
    $this->user->update(['password' => bcrypt('password123')]);
    Sanctum::actingAs($this->user);

    // Create multiple tokens
    $this->user->createToken('token1');
    $this->user->createToken('token2');

    expect($this->user->tokens()->count())->toBeGreaterThan(0);

    $response = postJson('/api/v1/profile', [
        'password' => 'password123',
        '_method' => 'DELETE',
    ]);

    $response->assertStatus(200);

    $this->user->refresh();
    expect($this->user->tokens()->count())->toBe(0);
});
