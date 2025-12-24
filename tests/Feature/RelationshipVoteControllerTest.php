<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\PostRelationship;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->sourcePost = Post::factory()->create();
    $this->targetPost = Post::factory()->create();
    $this->relationship = PostRelationship::factory()->create([
        'source_post_id' => $this->sourcePost->id,
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
    ]);
});

test('vote registers positive vote on relationship', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'status',
        'stats',
    ]);
});

test('vote registers negative vote on relationship', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => -1,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'status',
        'stats',
    ]);
});

test('vote requires authentication', function (): void {
    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    $response->assertStatus(401);
});

test('vote validates vote requerido', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", []);

    $response->assertStatus(422);
});

test('vote validates valores permitidos', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 2,
    ]);

    $response->assertStatus(422);
});

test('vote only accepts 1 or -1', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 0,
    ]);

    $response->assertStatus(422);
});

test('vote includes statistics in response', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    $response->assertStatus(201);
    expect($response->json('stats'))->toBeArray();
});

test('vote allows changing vote from positive to negative', function (): void {
    Sanctum::actingAs($this->user);

    // First vote
    postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    // Change vote
    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => -1,
    ]);

    $response->assertStatus(201);
});

test('vote allows changing vote from negative to positive', function (): void {
    Sanctum::actingAs($this->user);

    // First vote
    postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => -1,
    ]);

    // Change vote
    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    $response->assertStatus(201);
});

test('vote allows removing vote by voting again with same value', function (): void {
    Sanctum::actingAs($this->user);

    // First vote
    postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    // Vote again with same value to remove
    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    $response->assertStatus(200);
    expect($response->json('status'))->toBe('removed');
});

test('stats returns vote statistics', function (): void {
    $response = getJson("/api/v1/relationships/{$this->relationship->id}/votes");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data',
    ]);
});

test('stats does not require authentication', function (): void {
    $response = getJson("/api/v1/relationships/{$this->relationship->id}/votes");

    $response->assertStatus(200);
});

test('stats returns empty data for relationship without votes', function (): void {
    $response = getJson("/api/v1/relationships/{$this->relationship->id}/votes");

    $response->assertStatus(200);
    expect($response->json('data'))->toBeArray();
});

test('stats reflects user vote', function (): void {
    Sanctum::actingAs($this->user);

    // Cast a vote
    postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    // Check stats
    $response = getJson("/api/v1/relationships/{$this->relationship->id}/votes");

    $response->assertStatus(200);
});

test('vote handles non-existent relationship', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/relationships/99999/vote', [
        'vote' => 1,
    ]);

    $response->assertStatus(400);
});

test('stats handles non-existent relationship', function (): void {
    $response = getJson('/api/v1/relationships/99999/votes');

    $response->assertStatus(200);
});

test('vote updates contadores correctamente', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Sanctum::actingAs($user1);
    postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    Sanctum::actingAs($user2);
    postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    $response = getJson("/api/v1/relationships/{$this->relationship->id}/votes");

    $response->assertStatus(200);
});

test('vote accepts vote as integer', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 1,
    ]);

    $response->assertStatus(201);
});

test('vote rechaza voto como string', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/relationships/{$this->relationship->id}/vote", [
        'vote' => 'yes',
    ]);

    $response->assertStatus(422);
});
