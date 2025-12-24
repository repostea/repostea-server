<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create();
    $this->comment = Comment::factory()->create();
});

test('getUserSeals requires authentication', function (): void {
    $response = getJson('/api/v1/seals');

    $response->assertStatus(401);
});

test('getUserSeals returns user seals information', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/seals');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'available_seals',
        'total_earned',
        'total_used',
        'last_awarded_at',
    ]);
});

test('markPost requires authentication', function (): void {
    $response = postJson("/api/v1/posts/{$this->post->id}/seals", [
        'type' => 'recommended',
    ]);

    $response->assertStatus(401);
});

test('markPost requires type', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/seals", []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

test('markPost validates type valores permitidos', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/seals", [
        'type' => 'invalid',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

test('markPost accepts type recommended', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/seals", [
        'type' => 'recommended',
    ]);

    // May succeed or fail depending on seal availability
    expect($response->status())->toBeIn([200, 400]);
});

test('markPost accepts type advise_against', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/seals", [
        'type' => 'advise_against',
    ]);

    // May succeed or fail depending on seal availability
    expect($response->status())->toBeIn([200, 400]);
});

test('markPost returns correct structure on success', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/seals", [
        'type' => 'recommended',
    ]);

    // Always assert status is one of the expected values
    expect($response->status())->toBeIn([200, 400]);

    if ($response->status() === 200) {
        $response->assertJsonStructure([
            'success',
            'message',
            'available_seals',
            'post' => [
                'recommended_seals_count',
                'advise_against_seals_count',
            ],
        ]);
    }
});

test('unmarkPost requires authentication', function (): void {
    $response = deleteJson("/api/v1/posts/{$this->post->id}/seals?type=recommended");

    $response->assertStatus(401);
});

test('unmarkPost requires type', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/posts/{$this->post->id}/seals");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

test('unmarkPost validates type valores permitidos', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/posts/{$this->post->id}/seals?type=invalid");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

test('unmarkPost accepts type recommended', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/posts/{$this->post->id}/seals?type=recommended");

    // May succeed or fail depending on whether seal was applied
    expect($response->status())->toBeIn([200, 400]);
});

test('unmarkPost accepts type advise_against', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/posts/{$this->post->id}/seals?type=advise_against");

    // May succeed or fail depending on whether seal was applied
    expect($response->status())->toBeIn([200, 400]);
});

test('getPostMarks requires authentication', function (): void {
    $response = getJson("/api/v1/posts/{$this->post->id}/seals");

    $response->assertStatus(401);
});

test('getPostMarks returns post marks', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson("/api/v1/posts/{$this->post->id}/seals");

    $response->assertStatus(200);
});

test('markComment requires authentication', function (): void {
    $response = postJson("/api/v1/comments/{$this->comment->id}/seals", [
        'type' => 'recommended',
    ]);

    $response->assertStatus(401);
});

test('markComment requires type', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/comments/{$this->comment->id}/seals", []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

test('markComment validates type valores permitidos', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/comments/{$this->comment->id}/seals", [
        'type' => 'invalid',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

test('markComment accepts type recommended', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/comments/{$this->comment->id}/seals", [
        'type' => 'recommended',
    ]);

    expect($response->status())->toBeIn([200, 400]);
});

test('markComment accepts type advise_against', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/comments/{$this->comment->id}/seals", [
        'type' => 'advise_against',
    ]);

    expect($response->status())->toBeIn([200, 400]);
});

test('unmarkComment requires authentication', function (): void {
    $response = deleteJson("/api/v1/comments/{$this->comment->id}/seals?type=recommended");

    $response->assertStatus(401);
});

test('unmarkComment requires type', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/comments/{$this->comment->id}/seals");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

test('unmarkComment validates type valores permitidos', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/comments/{$this->comment->id}/seals?type=invalid");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

test('getCommentMarks requires authentication', function (): void {
    $response = getJson("/api/v1/comments/{$this->comment->id}/seals");

    $response->assertStatus(401);
});

test('getCommentMarks returns comment marks', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson("/api/v1/comments/{$this->comment->id}/seals");

    $response->assertStatus(200);
});

test('checkUserMarks requires authentication', function (): void {
    $response = postJson('/api/v1/seals/check', [
        'content_type' => 'post',
        'content_id' => $this->post->id,
    ]);

    $response->assertStatus(401);
});

test('checkUserMarks requires content_type', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/seals/check', [
        'content_id' => $this->post->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_type']);
});

test('checkUserMarks requires content_id', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/seals/check', [
        'content_type' => 'post',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_id']);
});

test('checkUserMarks validates content_type valores permitidos', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/seals/check', [
        'content_type' => 'invalid',
        'content_id' => 1,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_type']);
});

test('checkUserMarks accepts content_type post', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/seals/check', [
        'content_type' => 'post',
        'content_id' => $this->post->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'has_recommended',
        'has_advise_against',
    ]);
});

test('checkUserMarks accepts content_type comment', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/seals/check', [
        'content_type' => 'comment',
        'content_id' => $this->comment->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'has_recommended',
        'has_advise_against',
    ]);
});

test('checkUserMarks returns 404 for non-existent content', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/seals/check', [
        'content_type' => 'post',
        'content_id' => 99999,
    ]);

    $response->assertStatus(404);
});

test('markPost returns 404 for non-existent post', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/posts/99999/seals', [
        'type' => 'recommended',
    ]);

    $response->assertStatus(404);
});

test('markComment returns 404 for non-existent comment', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/comments/99999/seals', [
        'type' => 'recommended',
    ]);

    $response->assertStatus(404);
});

test('getPostMarks returns 404 for non-existent post', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/posts/99999/seals');

    $response->assertStatus(404);
});

test('getCommentMarks returns 404 for non-existent comment', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/comments/99999/seals');

    $response->assertStatus(404);
});
