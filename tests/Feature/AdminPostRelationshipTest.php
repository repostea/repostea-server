<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\PostRelationship;
use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    // Create admin role first
    $adminRole = Role::create([
        'name' => 'admin',
        'slug' => 'admin',
        'display_name' => 'Administrator',
        'description' => 'Administrator role for testing',
    ]);

    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();
    $this->post1 = Post::factory()->create(['user_id' => $this->user->id]);
    $this->post2 = Post::factory()->create(['user_id' => $this->user->id]);
});

test('admin can list all relationships with pagination', function (): void {
    PostRelationship::factory()->count(25)->create();

    actingAs($this->admin, 'sanctum');

    $response = getJson('/api/v1/admin/post-relationships?per_page=10');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'source_post_id',
                    'target_post_id',
                    'relationship_type',
                    'created_by',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ])
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 25);
});

test('admin can filter relationships by type', function (): void {
    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    actingAs($this->admin, 'sanctum');

    $response = getJson('/api/v1/admin/post-relationships?type=reply');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.relationship_type', 'reply');
});

test('admin can filter relationships by post id', function (): void {
    $post3 = Post::factory()->create(['user_id' => $this->user->id]);

    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    PostRelationship::create([
        'source_post_id' => $post3->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    actingAs($this->admin, 'sanctum');

    $response = getJson("/api/v1/admin/post-relationships?post_id={$this->post1->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can get relationship statistics', function (): void {
    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    actingAs($this->admin, 'sanctum');

    $response = getJson('/api/v1/admin/post-relationships/statistics');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'total_relationships',
                'by_type',
                'recent_relationships',
                'posts_with_relationships',
            ],
        ])
        ->assertJsonPath('data.total_relationships', 2);
});

test('admin can delete any relationship', function (): void {
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

    actingAs($this->admin, 'sanctum');

    $response = deleteJson("/api/v1/admin/post-relationships/{$relationship->id}");

    $response->assertOk()
        ->assertJson([
            'message' => __('relationships.success.deleted'),
        ]);

    expect(PostRelationship::count())->toBe(0);
});

test('admin can bulk delete relationships', function (): void {
    $rel1 = PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $rel2 = PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    // Create bidirectional for each
    PostRelationship::create([
        'source_post_id' => $this->post2->id,
        'target_post_id' => $this->post1->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    PostRelationship::create([
        'source_post_id' => $this->post2->id,
        'target_post_id' => $this->post1->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    actingAs($this->admin, 'sanctum');

    $response = postJson('/api/v1/admin/post-relationships/bulk-destroy', [
        'relationship_ids' => [$rel1->id, $rel2->id],
    ]);

    $response->assertOk()
        ->assertJsonPath('count', 2);

    expect(PostRelationship::count())->toBe(0);
});

test('admin can audit relationships', function (): void {
    // Create a normal relationship
    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    actingAs($this->admin, 'sanctum');

    $response = getJson('/api/v1/admin/post-relationships/audit');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'has_issues',
        ]);
});

test('admin can cleanup orphaned relationships', function (): void {
    $deletedPost = Post::factory()->create(['user_id' => $this->user->id]);
    $deletedPostId = $deletedPost->id;

    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $deletedPostId,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $deletedPost->delete();

    actingAs($this->admin, 'sanctum');

    $response = postJson('/api/v1/admin/post-relationships/cleanup');

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'deleted_count',
        ]);

    expect(PostRelationship::count())->toBe(0);
});

test('non-admin cannot access admin endpoints', function (): void {
    actingAs($this->user, 'sanctum');

    getJson('/api/v1/admin/post-relationships')->assertForbidden();
    getJson('/api/v1/admin/post-relationships/statistics')->assertForbidden();
    getJson('/api/v1/admin/post-relationships/audit')->assertForbidden();
    postJson('/api/v1/admin/post-relationships/cleanup')->assertForbidden();
});

test('unauthenticated user cannot access admin endpoints', function (): void {
    getJson('/api/v1/admin/post-relationships')->assertUnauthorized();
    getJson('/api/v1/admin/post-relationships/statistics')->assertUnauthorized();
});
