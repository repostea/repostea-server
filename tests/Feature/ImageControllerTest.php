<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

test('uploadAvatar requires authentication', function (): void {
    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = postJson('/api/v1/user/avatar', [
        'avatar' => $file,
    ]);

    $response->assertStatus(401);
});

test('uploadAvatar validates archivo requerido', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/user/avatar', []);

    $response->assertStatus(422);
});

test('uploadAvatar validates file type', function (): void {
    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->create('document.pdf', 100);

    $response = postJson('/api/v1/user/avatar', [
        'avatar' => $file,
    ]);

    $response->assertStatus(422);
});

test('uploadAvatar validates maximum size 16MB', function (): void {
    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->create('avatar.jpg', 20000, 'image/jpeg'); // 20MB

    $response = postJson('/api/v1/user/avatar', [
        'avatar' => $file,
    ]);

    $response->assertStatus(422);
});

test('deleteAvatar requires authentication', function (): void {
    $response = deleteJson('/api/v1/user/avatar');

    $response->assertStatus(401);
});

test('deleteAvatar returns 200', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson('/api/v1/user/avatar');

    $response->assertStatus(200);
    $response->assertJsonPath('message', 'Avatar deleted successfully.');
});

test('deleteAvatar clears user avatar fields when no image', function (): void {
    Sanctum::actingAs($this->user);

    $this->user->avatar = 'old_avatar.jpg';
    $this->user->avatar_url = 'old_avatar_small.jpg';
    $this->user->save();

    deleteJson('/api/v1/user/avatar');

    $this->user->refresh();
    expect($this->user->avatar)->toBeNull();
    expect($this->user->avatar_url)->toBeNull();
});

test('uploadThumbnail requires authentication', function (): void {
    $post = Post::factory()->create(['user_id' => $this->user->id]);

    $file = UploadedFile::fake()->image('thumbnail.jpg');

    $response = postJson("/api/v1/posts/{$post->id}/thumbnail", [
        'thumbnail' => $file,
    ]);

    $response->assertStatus(401);
});

test('uploadThumbnail validates archivo requerido', function (): void {
    $post = Post::factory()->create(['user_id' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$post->id}/thumbnail", []);

    $response->assertStatus(422);
});

test('uploadThumbnail validates file type', function (): void {
    $post = Post::factory()->create(['user_id' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->create('document.pdf', 100);

    $response = postJson("/api/v1/posts/{$post->id}/thumbnail", [
        'thumbnail' => $file,
    ]);

    $response->assertStatus(422);
});

test('uploadThumbnail only allows author subir', function (): void {
    $otherUser = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $otherUser->id]);

    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->image('thumbnail.jpg');

    $response = postJson("/api/v1/posts/{$post->id}/thumbnail", [
        'thumbnail' => $file,
    ]);

    $response->assertStatus(403);
});

test('uploadThumbnail validates maximum size 16MB', function (): void {
    $post = Post::factory()->create(['user_id' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->create('thumbnail.jpg', 20000, 'image/jpeg'); // 20MB

    $response = postJson("/api/v1/posts/{$post->id}/thumbnail", [
        'thumbnail' => $file,
    ]);

    $response->assertStatus(422);
});

test('deleteThumbnail requires authentication', function (): void {
    $post = Post::factory()->create(['user_id' => $this->user->id]);

    $response = deleteJson("/api/v1/posts/{$post->id}/thumbnail");

    $response->assertStatus(401);
});

test('deleteThumbnail only allows author eliminar', function (): void {
    $otherUser = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $otherUser->id]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/posts/{$post->id}/thumbnail");

    $response->assertStatus(403);
});

test('deleteThumbnail returns 200', function (): void {
    $post = Post::factory()->create(['user_id' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/posts/{$post->id}/thumbnail");

    $response->assertStatus(200);
    $response->assertJsonPath('message', 'Thumbnail deleted successfully.');
});

test('deleteThumbnail clears post thumbnail fields when no image', function (): void {
    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'thumbnail_url' => 'old_thumbnail.jpg',
    ]);

    Sanctum::actingAs($this->user);

    deleteJson("/api/v1/posts/{$post->id}/thumbnail");

    $post->refresh();
    expect($post->thumbnail_url)->toBeNull();
});

test('uploadInlineImage requires authentication', function (): void {
    $file = UploadedFile::fake()->image('inline.jpg');

    $response = postJson('/api/v1/images/inline', [
        'image' => $file,
    ]);

    $response->assertStatus(401);
});

test('uploadInlineImage validates archivo requerido', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/images/inline', []);

    $response->assertStatus(422);
});

test('uploadInlineImage validates file type', function (): void {
    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->create('document.pdf', 100);

    $response = postJson('/api/v1/images/inline', [
        'image' => $file,
    ]);

    $response->assertStatus(422);
});

test('uploadInlineImage validates maximum size 16MB', function (): void {
    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->create('inline.jpg', 20000, 'image/jpeg'); // 20MB

    $response = postJson('/api/v1/images/inline', [
        'image' => $file,
    ]);

    $response->assertStatus(422);
});

test('uploadInlineImage validates uploadable_type valores', function (): void {
    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->image('inline.jpg');

    $response = postJson('/api/v1/images/inline', [
        'image' => $file,
        'uploadable_type' => 'invalid',
    ]);

    $response->assertStatus(422);
});

test('serve returns 404 for non-existent hash with any size', function (): void {
    // Size parameter is ignored (IPX handles resizing on frontend)
    // All sizes serve the same image, so any size value is accepted
    $response = getJson('/api/v1/images/somehash/anysize');

    $response->assertStatus(404);
});

test('uploadThumbnail returns 404 for non-existent post', function (): void {
    Sanctum::actingAs($this->user);

    $file = UploadedFile::fake()->image('thumbnail.jpg');

    $response = postJson('/api/v1/posts/99999/thumbnail', [
        'thumbnail' => $file,
    ]);

    $response->assertStatus(404);
});

test('deleteThumbnail returns 404 for non-existent post', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson('/api/v1/posts/99999/thumbnail');

    $response->assertStatus(404);
});
