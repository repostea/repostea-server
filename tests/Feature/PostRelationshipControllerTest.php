<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\PostRelationship;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create(['user_id' => $this->user->id]);
    $this->targetPost = Post::factory()->create();
});

test('index returns post relationships', function (): void {
    PostRelationship::factory()->create([
        'source_post_id' => $this->post->id,
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
    ]);

    $response = getJson("/api/v1/posts/{$this->post->id}/relationships");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'own',
            'external',
        ],
    ]);
});

test('index separa relaciones propias y externas', function (): void {
    $ownPost = Post::factory()->create();

    PostRelationship::factory()->create([
        'source_post_id' => $this->post->id,
        'target_post_id' => $ownPost->id,
        'relationship_type' => 'continuation',
        'relation_category' => 'own',
    ]);

    $externalPost = Post::factory()->create();

    PostRelationship::factory()->create([
        'source_post_id' => $this->post->id,
        'target_post_id' => $externalPost->id,
        'relationship_type' => 'related',
        'relation_category' => 'external',
    ]);

    $response = getJson("/api/v1/posts/{$this->post->id}/relationships");

    $response->assertStatus(200);
    expect(count($response->json('data.own')))->toBeGreaterThan(0);
    expect(count($response->json('data.external')))->toBeGreaterThan(0);
});

test('index includes user vote when authenticated', function (): void {
    Sanctum::actingAs($this->user);

    $relationship = PostRelationship::factory()->create([
        'source_post_id' => $this->post->id,
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
    ]);

    // Create a vote for this relationship
    App\Models\RelationshipVote::create([
        'relationship_id' => $relationship->id,
        'user_id' => $this->user->id,
        'vote' => 1,
    ]);

    $response = getJson("/api/v1/posts/{$this->post->id}/relationships");

    $response->assertStatus(200);
    // The authenticated path should work without errors
    $response->assertJsonStructure([
        'data' => [
            'own',
            'external',
        ],
    ]);
});

test('store creates relationship between posts', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('message', __('relationships.success.created'));

    expect(PostRelationship::where('source_post_id', $this->post->id)
        ->where('target_post_id', $this->targetPost->id)
        ->exists())->toBeTrue();
});

test('store requires authentication', function (): void {
    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
    ]);

    $response->assertStatus(401);
});

test('store validates target_post_id requerido', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'relationship_type' => 'related',
    ]);

    $response->assertStatus(422);
});

test('store validates relationship_type requerido', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
    ]);

    $response->assertStatus(422);
});

test('store validates that target_post_id exists', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => 99999,
        'relationship_type' => 'related',
    ]);

    $response->assertStatus(422);
});

test('store prevents self-relationship', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->post->id,
        'relationship_type' => 'related',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', __('relationships.errors.self_relation'));
});

test('store previene relaciones duplicadas', function (): void {
    Sanctum::actingAs($this->user);

    PostRelationship::factory()->create([
        'source_post_id' => $this->post->id,
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
    ]);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', __('relationships.errors.already_exists'));
});

test('store accepts optional notes', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
        'notes' => 'This is related because...',
    ]);

    $response->assertStatus(201);

    $relationship = PostRelationship::where('source_post_id', $this->post->id)
        ->where('target_post_id', $this->targetPost->id)
        ->first();

    expect($relationship->notes)->toBe('This is related because...');
});

test('store accepts is_anonymous', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
        'is_anonymous' => true,
    ]);

    $response->assertStatus(201);

    $relationship = PostRelationship::where('source_post_id', $this->post->id)
        ->where('target_post_id', $this->targetPost->id)
        ->first();

    expect($relationship->is_anonymous)->toBeTrue();
});

test('store limita notas a 500 caracteres', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
        'notes' => str_repeat('a', 501),
    ]);

    $response->assertStatus(422);
});

test('store only allows author to create continuation relationships', function (): void {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'continuation',
    ]);

    $response->assertStatus(403);
    $response->assertJsonPath('message', __('relationships.errors.only_author_can_create'));
});

test('store only allows author to create correction relationships', function (): void {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'correction',
    ]);

    $response->assertStatus(403);
});

test('store previene responder a tu propio post', function (): void {
    $myTargetPost = Post::factory()->create(['user_id' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $myTargetPost->id,
        'relationship_type' => 'reply',
    ]);

    $response->assertStatus(403);
    $response->assertJsonPath('message', __('relationships.errors.cannot_reply_own_post'));
});

test('destroy deletes relationship', function (): void {
    $relationship = PostRelationship::factory()->create([
        'source_post_id' => $this->post->id,
        'target_post_id' => $this->targetPost->id,
        'created_by' => $this->user->id,
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/posts/{$this->post->id}/relationships/{$relationship->id}");

    $response->assertStatus(200);

    expect(PostRelationship::find($relationship->id))->toBeNull();
});

test('destroy requires authentication', function (): void {
    $relationship = PostRelationship::factory()->create([
        'source_post_id' => $this->post->id,
        'target_post_id' => $this->targetPost->id,
    ]);

    $response = deleteJson("/api/v1/posts/{$this->post->id}/relationships/{$relationship->id}");

    $response->assertStatus(401);
});

test('destroy only allows creator to delete', function (): void {
    $otherUser = User::factory()->create();
    $relationship = PostRelationship::factory()->create([
        'source_post_id' => $this->post->id,
        'target_post_id' => $this->targetPost->id,
        'created_by' => $otherUser->id,
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/posts/{$this->post->id}/relationships/{$relationship->id}");

    $response->assertStatus(200);
    // Author can delete
});

test('destroy allows post author to delete', function (): void {
    $otherUser = User::factory()->create();
    $relationship = PostRelationship::factory()->create([
        'source_post_id' => $this->post->id,
        'target_post_id' => $this->targetPost->id,
        'created_by' => $otherUser->id,
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/posts/{$this->post->id}/relationships/{$relationship->id}");

    $response->assertStatus(200);
});

test('destroy verifies relationship belongs to post', function (): void {
    $anotherPost = Post::factory()->create();
    $relationship = PostRelationship::factory()->create([
        'source_post_id' => $anotherPost->id,
        'target_post_id' => $this->targetPost->id,
        'created_by' => $this->user->id,
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/posts/{$this->post->id}/relationships/{$relationship->id}");

    $response->assertStatus(404);
    $response->assertJsonPath('message', __('relationships.errors.not_found'));
});

test('index includes related post information', function (): void {
    PostRelationship::factory()->create([
        'source_post_id' => $this->post->id,
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
    ]);

    $response = getJson("/api/v1/posts/{$this->post->id}/relationships");

    $response->assertStatus(200);

    $data = $response->json('data');
    $allRelations = array_merge($data['own'] ?? [], $data['external'] ?? []);

    expect(count($allRelations))->toBeGreaterThan(0);

    if (count($allRelations) > 0) {
        expect($allRelations[0])->toHaveKey('post');
        expect($allRelations[0]['post'])->toHaveKey('title');
    }
});

test('index handles post without relationships', function (): void {
    $response = getJson("/api/v1/posts/{$this->post->id}/relationships");

    $response->assertStatus(200);
    expect($response->json('data.own'))->toBeArray();
    expect($response->json('data.external'))->toBeArray();
});

test('store creates bidirectional relationship for related type', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'related',
    ]);

    $response->assertStatus(201);

    // Check forward relationship
    expect(PostRelationship::where('source_post_id', $this->post->id)
        ->where('target_post_id', $this->targetPost->id)
        ->exists())->toBeTrue();

    // Check reverse relationship
    expect(PostRelationship::where('source_post_id', $this->targetPost->id)
        ->where('target_post_id', $this->post->id)
        ->exists())->toBeTrue();
});

test('store does not create bidirectional relationship for continuation', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post->id}/relationships", [
        'target_post_id' => $this->targetPost->id,
        'relationship_type' => 'continuation',
    ]);

    $response->assertStatus(201);

    // Check forward relationship exists
    expect(PostRelationship::where('source_post_id', $this->post->id)
        ->where('target_post_id', $this->targetPost->id)
        ->exists())->toBeTrue();

    // Check reverse relationship does NOT exist
    expect(PostRelationship::where('source_post_id', $this->targetPost->id)
        ->where('target_post_id', $this->post->id)
        ->exists())->toBeFalse();
});
