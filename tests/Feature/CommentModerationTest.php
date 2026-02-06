<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use App\Models\RemoteUser;
use App\Models\Role;
use App\Models\Sub;
use App\Models\SubModerator;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    // Create admin role if it doesn't exist (required for admin() factory state)
    Role::firstOrCreate(['slug' => 'admin'], ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Administrator role']);

    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();
    $this->moderator = User::factory()->create();
    $this->postAuthor = User::factory()->create();
    $this->regularUser = User::factory()->create();

    // Create a sub with the moderator
    $this->sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    SubModerator::create([
        'sub_id' => $this->sub->id,
        'user_id' => $this->moderator->id,
    ]);

    // Create a post in the sub
    $this->post = Post::factory()->create([
        'user_id' => $this->postAuthor->id,
        'sub_id' => $this->sub->id,
    ]);
});

afterEach(function (): void {
    RemoteUser::query()->delete();
});

describe('Comment Moderation', function (): void {
    describe('local comments', function (): void {
        it('allows admin to hide a comment', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => $this->regularUser->id,
                'post_id' => $this->post->id,
                'status' => 'published',
            ]);

            actingAs($this->admin, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'hide',
                'reason' => 'Violates community guidelines',
            ]);

            $response->assertOk();
            expect($comment->fresh()->status)->toBe('hidden');
            expect($comment->fresh()->moderation_reason)->toBe('Violates community guidelines');
            expect($comment->fresh()->moderated_by)->toBe($this->admin->id);
        });

        it('allows moderator to hide a comment in their sub', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => $this->regularUser->id,
                'post_id' => $this->post->id,
                'status' => 'published',
            ]);

            actingAs($this->moderator, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'hide',
            ]);

            $response->assertOk();
            expect($comment->fresh()->status)->toBe('hidden');
        });

        it('allows post author to hide comments on their post', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => $this->regularUser->id,
                'post_id' => $this->post->id,
                'status' => 'published',
            ]);

            actingAs($this->postAuthor, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'hide',
            ]);

            $response->assertOk();
            expect($comment->fresh()->status)->toBe('hidden');
        });

        it('denies regular user from moderating comments', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => $this->user->id,
                'post_id' => $this->post->id,
                'status' => 'published',
            ]);

            actingAs($this->regularUser, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'hide',
            ]);

            $response->assertForbidden();
        });

        it('allows unhiding a hidden comment', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => $this->regularUser->id,
                'post_id' => $this->post->id,
                'status' => 'hidden',
                'moderated_by' => $this->admin->id,
                'moderated_at' => now(),
            ]);

            actingAs($this->admin, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'unhide',
            ]);

            $response->assertOk();
            expect($comment->fresh()->status)->toBe('published');
            expect($comment->fresh()->moderated_by)->toBeNull();
        });

        it('allows deleting a comment by moderator', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => $this->regularUser->id,
                'post_id' => $this->post->id,
                'status' => 'published',
                'content' => 'Original content',
            ]);

            actingAs($this->admin, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'delete',
                'reason' => 'Spam',
            ]);

            $response->assertOk();
            expect($comment->fresh()->status)->toBe('deleted_by_moderator');
            expect($comment->fresh()->content)->toBe('[deleted by moderator]');
        });

        it('allows restoring a hidden comment', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => $this->regularUser->id,
                'post_id' => $this->post->id,
                'status' => 'hidden',
                'content' => 'Original content',
            ]);

            actingAs($this->admin, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'restore',
            ]);

            $response->assertOk();
            expect($comment->fresh()->status)->toBe('published');
        });
    });

    describe('remote comments (federated)', function (): void {
        beforeEach(function (): void {
            // Create a remote user with all required fields
            $this->remoteUser = RemoteUser::create([
                'actor_uri' => 'https://mastodon.social/users/alice',
                'username' => 'alice',
                'domain' => 'mastodon.social',
                'display_name' => 'Alice from Mastodon',
            ]);
        });

        it('allows admin to hide a remote comment', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => null,
                'remote_user_id' => $this->remoteUser->id,
                'post_id' => $this->post->id,
                'status' => 'published',
                'content' => 'Remote comment content',
            ]);

            actingAs($this->admin, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'hide',
                'reason' => 'Off-topic',
            ]);

            $response->assertOk();
            expect($comment->fresh()->status)->toBe('hidden');
            expect($comment->fresh()->moderated_by)->toBe($this->admin->id);
        });

        it('allows sub moderator to hide a remote comment', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => null,
                'remote_user_id' => $this->remoteUser->id,
                'post_id' => $this->post->id,
                'status' => 'published',
            ]);

            actingAs($this->moderator, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'hide',
            ]);

            $response->assertOk();
            expect($comment->fresh()->status)->toBe('hidden');
        });

        it('allows post author to hide remote comments on their post', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => null,
                'remote_user_id' => $this->remoteUser->id,
                'post_id' => $this->post->id,
                'status' => 'published',
            ]);

            actingAs($this->postAuthor, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'hide',
            ]);

            $response->assertOk();
            expect($comment->fresh()->status)->toBe('hidden');
        });

        it('allows deleting a remote comment by moderator', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => null,
                'remote_user_id' => $this->remoteUser->id,
                'post_id' => $this->post->id,
                'status' => 'published',
                'content' => 'Spam from remote',
            ]);

            actingAs($this->admin, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'delete',
                'reason' => 'Spam',
            ]);

            $response->assertOk();
            expect($comment->fresh()->status)->toBe('deleted_by_moderator');
            expect($comment->fresh()->content)->toBe('[deleted by moderator]');
        });

        it('denies regular user from moderating remote comments', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => null,
                'remote_user_id' => $this->remoteUser->id,
                'post_id' => $this->post->id,
                'status' => 'published',
            ]);

            actingAs($this->regularUser, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'hide',
            ]);

            $response->assertForbidden();
        });
    });

    describe('validation', function (): void {
        it('requires a valid action', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => $this->regularUser->id,
                'post_id' => $this->post->id,
            ]);

            actingAs($this->admin, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'invalid_action',
            ]);

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['action']);
        });

        it('accepts optional reason', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => $this->regularUser->id,
                'post_id' => $this->post->id,
            ]);

            actingAs($this->admin, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'hide',
                // No reason provided
            ]);

            $response->assertOk();
        });

        it('limits reason length', function (): void {
            $comment = Comment::factory()->create([
                'user_id' => $this->regularUser->id,
                'post_id' => $this->post->id,
            ]);

            actingAs($this->admin, 'sanctum');
            $response = postJson("/api/v1/comments/{$comment->id}/moderate", [
                'action' => 'hide',
                'reason' => str_repeat('a', 501), // Exceeds 500 char limit
            ]);

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['reason']);
        });
    });
});
