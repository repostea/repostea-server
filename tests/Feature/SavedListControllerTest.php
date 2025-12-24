<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\SavedList;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

test('index returns user saved lists', function (): void {
    SavedList::factory()->count(3)->create(['user_id' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/lists');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'name', 'type'],
        ],
    ]);
});

test('store creates new custom list', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/lists', [
        'name' => 'My Custom List',
        'description' => 'A list of interesting posts',
        'is_public' => false,
        'type' => 'custom',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.name', 'My Custom List');

    expect(SavedList::where('name', 'My Custom List')->exists())->toBeTrue();
});

test('store requires authentication', function (): void {
    $response = postJson('/api/v1/lists', [
        'name' => 'Test List',
        'type' => 'custom',
    ]);

    $response->assertStatus(401);
});

test('store validates campos requeridos', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/lists', []);

    $response->assertStatus(422);
});

test('store prevents duplicate special lists', function (): void {
    SavedList::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'favorite',
        'name' => 'Favorites',
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/lists', [
        'name' => 'Another Favorites',
        'type' => 'favorite',
    ]);

    $response->assertStatus(422);
});

test('show returns list by UUID', function (): void {
    $list = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'is_public' => true,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson("/api/v1/lists/{$list->uuid}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.id', $list->id);
});

test('show returns list by slug', function (): void {
    $list = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'My Awesome List',
        'slug' => 'my-awesome-list',
        'is_public' => true,
        'type' => 'custom',
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/lists/my-awesome-list');

    $response->assertStatus(200);
    $response->assertJsonPath('data.id', $list->id);
});

test('show does not allow viewing other users private lists', function (): void {
    $otherUser = User::factory()->create();
    $list = SavedList::factory()->create([
        'user_id' => $otherUser->id,
        'is_public' => false,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson("/api/v1/lists/{$list->uuid}");

    $response->assertStatus(403);
});

test('update updates existing list', function (): void {
    $list = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Original Name',
        'type' => 'custom',
    ]);

    Sanctum::actingAs($this->user);

    $response = putJson("/api/v1/lists/{$list->uuid}", [
        'name' => 'Updated Name',
        'description' => 'Updated description',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.name', 'Updated Name');

    $list->refresh();
    expect($list->name)->toBe('Updated Name');
});

test('update does not allow editing other user list', function (): void {
    $otherUser = User::factory()->create();
    $list = SavedList::factory()->create(['user_id' => $otherUser->id]);

    Sanctum::actingAs($this->user);

    $response = putJson("/api/v1/lists/{$list->uuid}", [
        'name' => 'Hacked Name',
    ]);

    $response->assertStatus(403);
});

test('update does not allow changing special list type', function (): void {
    $list = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'favorite',
    ]);

    Sanctum::actingAs($this->user);

    $response = putJson("/api/v1/lists/{$list->uuid}", [
        'type' => 'custom',
    ]);

    $response->assertStatus(422);
});

test('destroy deletes custom list', function (): void {
    $list = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'custom',
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/lists/{$list->uuid}");

    $response->assertStatus(200);

    expect(SavedList::find($list->id))->toBeNull();
});

test('destroy does not allow deleting special lists', function (): void {
    $list = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'favorite',
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/lists/{$list->uuid}");

    $response->assertStatus(403);
});

test('destroy does not allow deleting other user list', function (): void {
    $otherUser = User::factory()->create();
    $list = SavedList::factory()->create([
        'user_id' => $otherUser->id,
        'type' => 'custom',
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/lists/{$list->uuid}");

    $response->assertStatus(403);
});

test('addPost adds post to list', function (): void {
    $list = SavedList::factory()->create(['user_id' => $this->user->id]);
    $post = Post::factory()->create();

    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/lists/{$list->uuid}/posts", [
        'post_id' => $post->id,
        'notes' => 'Interesting article',
    ]);

    $response->assertStatus(200);

    expect($list->posts()->where('post_id', $post->id)->exists())->toBeTrue();
});

test('addPost previene duplicados', function (): void {
    $list = SavedList::factory()->create(['user_id' => $this->user->id]);
    $post = Post::factory()->create();

    $list->posts()->attach($post->id);

    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/lists/{$list->uuid}/posts", [
        'post_id' => $post->id,
    ]);

    $response->assertStatus(422);
});

test('removePost removes post from list', function (): void {
    $list = SavedList::factory()->create(['user_id' => $this->user->id]);
    $post = Post::factory()->create();

    $list->posts()->attach($post->id);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/lists/{$list->uuid}/posts", [
        'post_id' => $post->id,
    ]);

    $response->assertStatus(200);

    expect($list->posts()->where('post_id', $post->id)->exists())->toBeFalse();
});

test('posts returns posts from a list', function (): void {
    $list = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'is_public' => true,
    ]);
    $posts = Post::factory()->count(3)->create();

    foreach ($posts as $post) {
        $list->posts()->attach($post->id);
    }

    Sanctum::actingAs($this->user);

    $response = getJson("/api/v1/lists/{$list->uuid}/posts");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'title'],
        ],
    ]);
});

test('toggleFavorite adds post to favorites', function (): void {
    $post = Post::factory()->create();

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/posts/toggle-favorite', [
        'post_id' => $post->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('is_favorite', true);
});

test('toggleFavorite removes post from favorites', function (): void {
    $post = Post::factory()->create();
    $favoritesList = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'favorite',
    ]);
    $favoritesList->posts()->attach($post->id);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/posts/toggle-favorite', [
        'post_id' => $post->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('is_favorite', false);
});

test('toggleReadLater adds post to read later', function (): void {
    $post = Post::factory()->create();

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/posts/toggle-read-later', [
        'post_id' => $post->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('is_read_later', true);
});

test('toggleReadLater removes post from read later', function (): void {
    $post = Post::factory()->create();
    $readLaterList = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'read_later',
    ]);
    $readLaterList->posts()->attach($post->id);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/posts/toggle-read-later', [
        'post_id' => $post->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('is_read_later', false);
});

test('checkSavedStatus returns post saved status', function (): void {
    $post = Post::factory()->create();

    Sanctum::actingAs($this->user);

    $response = getJson("/api/v1/posts/{$post->id}/saved-status");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'is_favorite',
        'is_read_later',
        'saved_lists',
    ]);
});

test('updatePostNotes updates post notes in list', function (): void {
    $list = SavedList::factory()->create(['user_id' => $this->user->id]);
    $post = Post::factory()->create();

    $list->posts()->attach($post->id, ['notes' => 'Original notes']);

    Sanctum::actingAs($this->user);

    $response = putJson("/api/v1/lists/{$list->uuid}/posts/notes", [
        'post_id' => $post->id,
        'notes' => 'Updated notes',
    ]);

    $response->assertStatus(200);

    $pivot = $list->posts()->where('post_id', $post->id)->first();
    expect($pivot->pivot->notes)->toBe('Updated notes');
});

test('updatePostNotes returns error if post not in list', function (): void {
    $list = SavedList::factory()->create(['user_id' => $this->user->id]);
    $post = Post::factory()->create();

    Sanctum::actingAs($this->user);

    $response = putJson("/api/v1/lists/{$list->uuid}/posts/notes", [
        'post_id' => $post->id,
        'notes' => 'Some notes',
    ]);

    $response->assertStatus(404);
});

test('clearList removes all posts from a list', function (): void {
    $list = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'custom',
    ]);
    $posts = Post::factory()->count(5)->create();

    foreach ($posts as $post) {
        $list->posts()->attach($post->id);
    }

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/lists/{$list->uuid}/posts/all");

    $response->assertStatus(200);

    expect($list->posts()->count())->toBe(0);
});

test('clearList does not allow clearing special lists', function (): void {
    $list = SavedList::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'favorite',
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/lists/{$list->uuid}/posts/all");

    $response->assertStatus(422);
});

test('store validates list types', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/lists', [
        'name' => 'Invalid Type List',
        'type' => 'invalid_type',
    ]);

    $response->assertStatus(422);
});

test('addPost validates that post exists', function (): void {
    $list = SavedList::factory()->create(['user_id' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/lists/{$list->uuid}/posts", [
        'post_id' => 99999,
    ]);

    $response->assertStatus(422);
});
