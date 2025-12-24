<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\PostRelationship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post1 = Post::factory()->create(['user_id' => $this->user->id]);
    $this->post2 = Post::factory()->create(['user_id' => $this->user->id]);
});

test('can create a post relationship', function (): void {
    $relationship = PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    expect($relationship)->toBeInstanceOf(PostRelationship::class)
        ->and($relationship->source_post_id)->toBe($this->post1->id)
        ->and($relationship->target_post_id)->toBe($this->post2->id)
        ->and($relationship->relationship_type)->toBe(PostRelationship::TYPE_REPLY);
});

test('relationship belongs to source post', function (): void {
    $relationship = PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    expect($relationship->sourcePost)->toBeInstanceOf(Post::class)
        ->and($relationship->sourcePost->id)->toBe($this->post1->id);
});

test('relationship belongs to target post', function (): void {
    $relationship = PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    expect($relationship->targetPost)->toBeInstanceOf(Post::class)
        ->and($relationship->targetPost->id)->toBe($this->post2->id);
});

test('relationship belongs to creator', function (): void {
    $relationship = PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    expect($relationship->creator)->toBeInstanceOf(User::class)
        ->and($relationship->creator->id)->toBe($this->user->id);
});

test('can get all relationship types', function (): void {
    $types = PostRelationship::getRelationshipTypes();

    expect($types)->toBeArray()
        ->and($types)->toContain(PostRelationship::TYPE_REPLY)
        ->and($types)->toContain(PostRelationship::TYPE_CONTINUATION)
        ->and($types)->toContain(PostRelationship::TYPE_RELATED)
        ->and($types)->toContain(PostRelationship::TYPE_UPDATE)
        ->and($types)->toContain(PostRelationship::TYPE_CORRECTION)
        ->and($types)->toContain(PostRelationship::TYPE_DUPLICATE)
        ->and(count($types))->toBe(6);
});

test('continuation requires author permission', function (): void {
    expect(PostRelationship::requiresAuthor(PostRelationship::TYPE_CONTINUATION))->toBeTrue()
        ->and(PostRelationship::requiresAuthor(PostRelationship::TYPE_REPLY))->toBeFalse()
        ->and(PostRelationship::requiresAuthor(PostRelationship::TYPE_RELATED))->toBeFalse();
});

test('can scope by relationship type', function (): void {
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

    $replies = PostRelationship::ofType(PostRelationship::TYPE_REPLY)->get();
    $related = PostRelationship::ofType(PostRelationship::TYPE_RELATED)->get();

    expect($replies)->toHaveCount(1)
        ->and($related)->toHaveCount(1)
        ->and($replies->first()->relationship_type)->toBe(PostRelationship::TYPE_REPLY)
        ->and($related->first()->relationship_type)->toBe(PostRelationship::TYPE_RELATED);
});

test('can scope for post relationships', function (): void {
    $post3 = Post::factory()->create(['user_id' => $this->user->id]);

    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    PostRelationship::create([
        'source_post_id' => $this->post2->id,
        'target_post_id' => $post3->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $post1Relationships = PostRelationship::forPost($this->post1->id)->get();
    $post2Relationships = PostRelationship::forPost($this->post2->id)->get();

    expect($post1Relationships)->toHaveCount(1)
        ->and($post2Relationships)->toHaveCount(2);
});

test('post can have relationships as source', function (): void {
    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $this->post1->load('relationshipsAsSource');

    expect($this->post1->relationshipsAsSource)->toHaveCount(1)
        ->and($this->post1->relationshipsAsSource->first()->target_post_id)->toBe($this->post2->id);
});

test('post can have relationships as target', function (): void {
    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $this->post2->load('relationshipsAsTarget');

    expect($this->post2->relationshipsAsTarget)->toHaveCount(1)
        ->and($this->post2->relationshipsAsTarget->first()->source_post_id)->toBe($this->post1->id);
});

test('post can get all relationships', function (): void {
    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
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

    $relationships = $this->post1->allRelationships();

    expect($relationships)->toHaveCount(2);
});

test('post can get related posts by type', function (): void {
    $post3 = Post::factory()->create(['user_id' => $this->user->id]);

    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $post3->id,
        'relationship_type' => PostRelationship::TYPE_RELATED,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $replies = $this->post1->getRelatedPosts(PostRelationship::TYPE_REPLY);
    $related = $this->post1->getRelatedPosts(PostRelationship::TYPE_RELATED);

    expect($replies)->toHaveCount(1)
        ->and($related)->toHaveCount(1)
        ->and($replies->first()->id)->toBe($this->post2->id)
        ->and($related->first()->id)->toBe($post3->id);
});

test('post can get continuation chain', function (): void {
    $post3 = Post::factory()->create(['user_id' => $this->user->id, 'title' => 'Part 3']);
    $post4 = Post::factory()->create(['user_id' => $this->user->id, 'title' => 'Part 4']);

    // Create continuation chain: post1 -> post2 -> post3 -> post4
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

    PostRelationship::create([
        'source_post_id' => $post4->id,
        'target_post_id' => $post3->id,
        'relationship_type' => PostRelationship::TYPE_CONTINUATION,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    $chain = $this->post2->getContinuationChain();

    expect($chain)->toHaveCount(4)
        ->and($chain[0]->id)->toBe($this->post1->id)
        ->and($chain[1]->id)->toBe($this->post2->id)
        ->and($chain[2]->id)->toBe($post3->id)
        ->and($chain[3]->id)->toBe($post4->id);
});

test('unique constraint prevents duplicate relationships', function (): void {
    PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]);

    expect(fn () => PostRelationship::create([
        'source_post_id' => $this->post1->id,
        'target_post_id' => $this->post2->id,
        'relationship_type' => PostRelationship::TYPE_REPLY,
        'relation_category' => 'own',
        'created_by' => $this->user->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});
