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
    $this->otherUser = User::factory()->create();
    $this->post1 = Post::factory()->create(['user_id' => $this->user->id, 'status' => 'published']);
    // post2 belongs to otherUser to allow reply relationships
    $this->post2 = Post::factory()->create(['user_id' => $this->otherUser->id, 'status' => 'published']);
});

test('can get post relationships', function (): void {
    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $response = getJson("/api/v1/posts/{$this->post1->id}/relationships");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'own' => [
                    '*' => [
                        'id',
                        'type',
                        'direction',
                        'post' => [
                            'id',
                            'title',
                            'slug',
                            'content',
                            'author',
                        ],
                        'created_by',
                        'created_at',
                    ],
                ],
                'external',
            ],
        ])
        ->assertJsonCount(1, 'data.own');
});

test('can create post relationship when authenticated', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", [
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'source_post_id',
                'target_post_id',
                'relationship_type',
                'created_by',
            ],
        ]);

    // Verify bidirectional relationship was created
    expect(PostRelationship::count())->toBe(2);
    expect(PostRelationship::where('source_post_id', $this->post1->id)->count())->toBe(1);
    expect(PostRelationship::where('source_post_id', $this->post2->id)->count())->toBe(1);
});

test('cannot create relationship when unauthenticated', function (): void {
    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", [
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
    ]);

    $response->assertUnauthorized();
});

test('cannot create self-relationship', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", [
        'target_post_id' => $this->post1->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
    ]);

    $response->assertUnprocessable()
        ->assertJson([
            'message' => 'A post cannot be related to itself',
        ]);
});

test('cannot create duplicate relationship', function (): void {
    Sanctum::actingAs($this->user);

    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", [
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
    ]);

    $response->assertUnprocessable()
        ->assertJson([
            'message' => 'This relationship already exists',
        ]);
});

test('only author can create continuation relationship', function (): void {
    Sanctum::actingAs($this->otherUser);

    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", [
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_CONTINUATION,
    ]);

    $response->assertForbidden()
        ->assertJson([
            'message' => __('relationships.errors.only_author_can_create'),
        ]);
});

test('author can create continuation relationship', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", [
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_CONTINUATION,
    ]);

    $response->assertCreated();
});

test('any user can create non-continuation relationships', function (): void {
    Sanctum::actingAs($this->otherUser);

    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", [
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
    ]);

    $response->assertCreated();
});

test('can delete relationship as creator', function (): void {
    Sanctum::actingAs($this->user);

    $relationship = PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    // Create bidirectional
    PostRelationship::create([
        'source_post_id' => $this->post2->id,
        'target_post_id' => $this->post1->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $response = deleteJson("/api/v1/posts/{$this->post1->id}/relationships/{$relationship->id}");

    $response->assertOk()
        ->assertJson([
            'message' => __('relationships.success.deleted'),
        ]);

    // Verify both sides were deleted
    expect(PostRelationship::count())->toBe(0);
});

test('can delete relationship as post author', function (): void {
    Sanctum::actingAs($this->user);

    $relationship = PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->otherUser->id,
    ]);

    $response = deleteJson("/api/v1/posts/{$this->post1->id}/relationships/{$relationship->id}");

    $response->assertOk();
});

test('cannot delete relationship as non-creator and non-author', function (): void {
    $anotherUser = User::factory()->create();
    Sanctum::actingAs($anotherUser);

    $relationship = PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $response = deleteJson("/api/v1/posts/{$this->post1->id}/relationships/{$relationship->id}");

    $response->assertForbidden();
});

test('can get continuation chain', function (): void {
    $post3 = Post::factory()->create(['user_id' => $this->user->id, 'status' => 'published']);

    PostRelationship::create([
        'source_post_id' => $this->post2->id,
        'target_post_id' => $this->post1->id,
        'relationship_type' => PostRelationship::TYPE_CONTINUATION,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    PostRelationship::create([
        'source_post_id' => $post3->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_CONTINUATION,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $response = getJson("/api/v1/posts/{$this->post2->id}/relationships/continuation-chain");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'slug',
                    'url',
                ],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('can get relationship types', function (): void {
    $response = getJson('/api/v1/relationship-types');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'own' => [
                    '*' => [
                        'value',
                        'label',
                        'category',
                        'requires_author',
                        'icon',
                        'description',
                    ],
                ],
                'external' => [
                    '*' => [
                        'value',
                        'label',
                        'category',
                        'requires_author',
                        'icon',
                        'description',
                    ],
                ],
            ],
        ]);
});

test('validates required fields when creating relationship', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['target_post_id', 'relationship_type']);
});

test('validates target post exists', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", [
        'target_post_id' => 99999,
        'relationship_type' => PostRelationship::TYPE_REPLY,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['target_post_id']);
});

test('validates relationship type is valid', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", [
        'target_post_id' => $this->post2->id,
        'relationship_type' => 'invalid_type',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['relationship_type']);
});

test('can include optional notes when creating relationship', function (): void {
    Sanctum::actingAs($this->user);

    $notes = 'This is a follow-up post';

    $response = postJson("/api/v1/posts/{$this->post1->id}/relationships", [
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'notes' => $notes,
    ]);

    $response->assertCreated();

    $relationship = PostRelationship::where('source_post_id', $this->post1->id)->first();
    expect($relationship->notes)->toBe($notes);
});
