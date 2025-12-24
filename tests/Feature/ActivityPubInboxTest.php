<?php

declare(strict_types=1);

use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubActorKey;
use App\Models\ActivityPubFollower;
use App\Models\Sub;
use App\Models\User;
use App\Services\MultiActorActivityPubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['activitypub.enabled' => true]);
    config(['activitypub.domain' => 'https://test.example.com']);
    config(['activitypub.public_domain' => 'https://test.example.com']);
    config(['activitypub.actor.username' => 'testinstance']);
    config(['activitypub.auto_accept_follows' => true]);
});

describe('Legacy Inbox (ActivityPubController)', function (): void {
    it('returns 404 when ActivityPub is disabled', function (): void {
        config(['activitypub.enabled' => false]);

        $response = $this->postJson('/activitypub/inbox', [
            'type' => 'Follow',
            'actor' => 'https://remote.example.com/users/alice',
        ]);

        $response->assertStatus(404);
    });

    it('accepts and returns 202 for valid activity', function (): void {
        $response = $this->postJson('/activitypub/inbox', [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Like',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => 'https://test.example.com/posts/1',
        ]);

        $response->assertStatus(202)
            ->assertJson(['status' => 'ok']);
    });

    it('ignores unknown activity types', function (): void {
        $response = $this->postJson('/activitypub/inbox', [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Announce',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => 'https://somewhere.example.com/posts/123',
        ]);

        $response->assertStatus(202);
    });

    it('handles Delete activity by removing follower', function (): void {
        // Create a follower first
        ActivityPubFollower::create([
            'actor_id' => 'https://remote.example.com/users/alice',
            'inbox_url' => 'https://remote.example.com/users/alice/inbox',
            'username' => 'alice',
            'domain' => 'remote.example.com',
            'followed_at' => now(),
        ]);

        expect(ActivityPubFollower::count())->toBe(1);

        $response = $this->postJson('/activitypub/inbox', [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Delete',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => 'https://remote.example.com/users/alice',
        ]);

        $response->assertStatus(202);
        expect(ActivityPubFollower::count())->toBe(0);
    });
});

describe('Multi-Actor User Inbox', function (): void {
    it('returns 404 for nonexistent user', function (): void {
        $response = $this->postJson('/activitypub/users/nonexistent/inbox', [
            'type' => 'Follow',
            'actor' => 'https://remote.example.com/users/alice',
        ]);

        $response->assertStatus(404);
    });

    it('returns 404 when ActivityPub is disabled', function (): void {
        config(['activitypub.enabled' => false]);

        $user = User::factory()->create(['username' => 'testuser']);
        ActivityPubActor::findOrCreateForUser($user);

        $response = $this->postJson('/activitypub/users/testuser/inbox', [
            'type' => 'Follow',
            'actor' => 'https://remote.example.com/users/alice',
        ]);

        $response->assertStatus(404);
    });

    it('accepts valid activity and returns 202', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        ActivityPubActor::findOrCreateForUser($user);

        $response = $this->postJson('/activitypub/users/testuser/inbox', [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Like',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => 'https://test.example.com/posts/1',
        ]);

        $response->assertStatus(202)
            ->assertJson(['status' => 'ok']);
    });

    it('handles Delete activity by removing follower', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        // Create follower
        ActivityPubActorFollower::create([
            'actor_id' => $actor->id,
            'follower_uri' => 'https://remote.example.com/users/alice',
            'follower_inbox' => 'https://remote.example.com/users/alice/inbox',
            'follower_username' => 'alice',
            'follower_domain' => 'remote.example.com',
            'followed_at' => now(),
        ]);

        expect(ActivityPubActorFollower::count())->toBe(1);

        $response = $this->postJson('/activitypub/users/testuser/inbox', [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Delete',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => 'https://remote.example.com/users/alice',
        ]);

        $response->assertStatus(202);
        expect(ActivityPubActorFollower::count())->toBe(0);
    });
});

describe('Multi-Actor Group Inbox', function (): void {
    it('returns 404 for nonexistent group', function (): void {
        $response = $this->postJson('/activitypub/groups/nonexistent/inbox', [
            'type' => 'Follow',
            'actor' => 'https://remote.example.com/users/alice',
        ]);

        $response->assertStatus(404);
    });

    it('accepts valid activity for group', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'testgroup',
            'display_name' => 'Test Group',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);
        ActivityPubActor::findOrCreateForSub($sub);

        $response = $this->postJson('/activitypub/groups/testgroup/inbox', [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Like',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => 'https://test.example.com/posts/1',
        ]);

        $response->assertStatus(202)
            ->assertJson(['status' => 'ok']);
    });

    it('handles Delete activity for group', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'testgroup',
            'display_name' => 'Test Group',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);
        $actor = ActivityPubActor::findOrCreateForSub($sub);

        // Create follower
        ActivityPubActorFollower::create([
            'actor_id' => $actor->id,
            'follower_uri' => 'https://remote.example.com/users/alice',
            'follower_inbox' => 'https://remote.example.com/users/alice/inbox',
            'follower_username' => 'alice',
            'follower_domain' => 'remote.example.com',
            'followed_at' => now(),
        ]);

        expect(ActivityPubActorFollower::count())->toBe(1);

        $response = $this->postJson('/activitypub/groups/testgroup/inbox', [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Delete',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => 'https://remote.example.com/users/alice',
        ]);

        $response->assertStatus(202);
        expect(ActivityPubActorFollower::count())->toBe(0);
    });
});

describe('Follow Activity Handling', function (): void {
    it('rejects Follow without actor', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        $service = app(MultiActorActivityPubService::class);
        $result = $service->handleFollow($actor, [
            'type' => 'Follow',
            'object' => $actor->actor_uri,
        ]);

        expect($result)->toBeFalse();
    });

    it('rejects Follow with empty actor', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        $service = app(MultiActorActivityPubService::class);
        $result = $service->handleFollow($actor, [
            'type' => 'Follow',
            'actor' => '',
            'object' => $actor->actor_uri,
        ]);

        expect($result)->toBeFalse();
    });

    it('rejects Follow when remote actor fetch fails', function (): void {
        Http::fake([
            'https://remote.example.com/*' => Http::response(null, 404),
        ]);

        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        $service = app(MultiActorActivityPubService::class);
        $result = $service->handleFollow($actor, [
            'type' => 'Follow',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => $actor->actor_uri,
        ]);

        expect($result)->toBeFalse();
        expect(ActivityPubActorFollower::count())->toBe(0);
    });

    it('creates follower using ActivityPubActorFollower model', function (): void {
        // Test the follower creation directly since handleFollow requires
        // real DNS resolution for SSRF protection (which is correct behavior)
        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        // Simulate what handleFollow does after fetching remote actor
        $remoteActorData = [
            'id' => 'https://remote.example.com/users/alice',
            'type' => 'Person',
            'preferredUsername' => 'alice',
            'name' => 'Alice',
            'inbox' => 'https://remote.example.com/users/alice/inbox',
            'endpoints' => [
                'sharedInbox' => 'https://remote.example.com/inbox',
            ],
        ];

        ActivityPubActorFollower::createFromRemoteActor(
            $actor,
            'https://remote.example.com/users/alice',
            $remoteActorData,
        );

        expect(ActivityPubActorFollower::count())->toBe(1);

        $follower = ActivityPubActorFollower::first();
        expect($follower->actor_id)->toBe($actor->id);
        expect($follower->follower_uri)->toBe('https://remote.example.com/users/alice');
        expect($follower->follower_username)->toBe('alice');
        expect($follower->follower_domain)->toBe('remote.example.com');
        expect($follower->follower_inbox)->toBe('https://remote.example.com/users/alice/inbox');
        expect($follower->follower_shared_inbox)->toBe('https://remote.example.com/inbox');
    });

    it('handles Follow with real domain (integration)', function (): void {
        // This test uses example.com which is a real resolvable domain
        // Note: This makes a real DNS lookup but no HTTP request due to Http::fake
        Http::fake([
            'https://example.com/*' => Http::response([
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => 'https://example.com/users/alice',
                'type' => 'Person',
                'preferredUsername' => 'alice',
                'name' => 'Alice',
                'inbox' => 'https://example.com/users/alice/inbox',
                'outbox' => 'https://example.com/users/alice/outbox',
            ], 200, ['Content-Type' => 'application/activity+json']),
        ]);

        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);
        ActivityPubActorKey::ensureForActor($actor);
        $actor->load('keys'); // Load keys relationship for signing

        $service = app(MultiActorActivityPubService::class);
        $result = $service->handleFollow($actor, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => 'https://example.com/activities/follow-123',
            'type' => 'Follow',
            'actor' => 'https://example.com/users/alice',
            'object' => $actor->actor_uri,
        ]);

        expect($result)->toBeTrue();
        expect(ActivityPubActorFollower::count())->toBe(1);
    });
});

describe('Undo Activity Handling', function (): void {
    it('removes follower on Undo Follow', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        // Create existing follower
        ActivityPubActorFollower::create([
            'actor_id' => $actor->id,
            'follower_uri' => 'https://remote.example.com/users/alice',
            'follower_inbox' => 'https://remote.example.com/users/alice/inbox',
            'follower_username' => 'alice',
            'follower_domain' => 'remote.example.com',
            'followed_at' => now(),
        ]);

        expect(ActivityPubActorFollower::count())->toBe(1);

        $service = app(MultiActorActivityPubService::class);
        $result = $service->handleUndo($actor, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Undo',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => [
                'type' => 'Follow',
                'actor' => 'https://remote.example.com/users/alice',
                'object' => $actor->actor_uri,
            ],
        ]);

        expect($result)->toBeTrue();
        expect(ActivityPubActorFollower::count())->toBe(0);
    });

    it('does nothing for Undo of non-Follow', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        $service = app(MultiActorActivityPubService::class);
        $result = $service->handleUndo($actor, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Undo',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => [
                'type' => 'Like',
                'actor' => 'https://remote.example.com/users/alice',
                'object' => 'https://test.example.com/posts/1',
            ],
        ]);

        expect($result)->toBeFalse();
    });

    it('handles Undo with missing actor gracefully', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        $service = app(MultiActorActivityPubService::class);
        $result = $service->handleUndo($actor, [
            'type' => 'Undo',
            'object' => [
                'type' => 'Follow',
            ],
        ]);

        expect($result)->toBeFalse();
    });

    it('handles Undo with string object gracefully', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        $service = app(MultiActorActivityPubService::class);
        $result = $service->handleUndo($actor, [
            'type' => 'Undo',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => 'https://remote.example.com/activities/follow-123',
        ]);

        expect($result)->toBeFalse();
    });

    it('only removes follower for correct actor', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        // Create followers for Alice and Bob
        ActivityPubActorFollower::create([
            'actor_id' => $actor->id,
            'follower_uri' => 'https://remote.example.com/users/alice',
            'follower_inbox' => 'https://remote.example.com/users/alice/inbox',
            'follower_username' => 'alice',
            'follower_domain' => 'remote.example.com',
            'followed_at' => now(),
        ]);

        ActivityPubActorFollower::create([
            'actor_id' => $actor->id,
            'follower_uri' => 'https://remote.example.com/users/bob',
            'follower_inbox' => 'https://remote.example.com/users/bob/inbox',
            'follower_username' => 'bob',
            'follower_domain' => 'remote.example.com',
            'followed_at' => now(),
        ]);

        expect(ActivityPubActorFollower::count())->toBe(2);

        $service = app(MultiActorActivityPubService::class);
        $service->handleUndo($actor, [
            'type' => 'Undo',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => [
                'type' => 'Follow',
                'actor' => 'https://remote.example.com/users/alice',
                'object' => $actor->actor_uri,
            ],
        ]);

        expect(ActivityPubActorFollower::count())->toBe(1);
        expect(ActivityPubActorFollower::first()->follower_uri)->toBe('https://remote.example.com/users/bob');
    });
});

describe('Activity validation', function (): void {
    it('handles malformed JSON gracefully', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        ActivityPubActor::findOrCreateForUser($user);

        $response = $this->post('/activitypub/users/testuser/inbox', [], [
            'Content-Type' => 'application/activity+json',
        ]);

        // Should not crash, returns 202
        $response->assertStatus(202);
    });

    it('handles activity without type', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);
        ActivityPubActor::findOrCreateForUser($user);

        $response = $this->postJson('/activitypub/users/testuser/inbox', [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'actor' => 'https://remote.example.com/users/alice',
        ]);

        $response->assertStatus(202);
    });
});

describe('Like Activity Handling', function (): void {
    it('increments federation_likes_count for valid Like', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'testsub',
            'display_name' => 'Test Sub',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);

        $post = App\Models\Post::factory()->create([
            'user_id' => $user->id,
            'sub_id' => $sub->id,
            'status' => 'published',
            'federation_likes_count' => 0,
        ]);

        $service = app(MultiActorActivityPubService::class);
        $domain = $service->getDomain();

        $result = $service->handleLike([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Like',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => "{$domain}/activitypub/notes/{$post->id}",
        ]);

        expect($result)->toBeTrue();
        expect($post->fresh()->federation_likes_count)->toBe(1);
    });

    it('ignores Like for unknown post', function (): void {
        $service = app(MultiActorActivityPubService::class);

        $result = $service->handleLike([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Like',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => 'https://test.example.com/activitypub/notes/99999',
        ]);

        expect($result)->toBeFalse();
    });

    it('ignores Like without actor', function (): void {
        $service = app(MultiActorActivityPubService::class);

        $result = $service->handleLike([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Like',
            'object' => 'https://test.example.com/activitypub/notes/1',
        ]);

        expect($result)->toBeFalse();
    });

    it('handles Like with object as array', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'testsub2',
            'display_name' => 'Test Sub 2',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);

        $post = App\Models\Post::factory()->create([
            'user_id' => $user->id,
            'sub_id' => $sub->id,
            'status' => 'published',
            'federation_likes_count' => 5,
        ]);

        $service = app(MultiActorActivityPubService::class);
        $domain = $service->getDomain();

        $result = $service->handleLike([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Like',
            'actor' => 'https://mastodon.social/users/bob',
            'object' => [
                'id' => "{$domain}/activitypub/notes/{$post->id}",
                'type' => 'Note',
            ],
        ]);

        expect($result)->toBeTrue();
        expect($post->fresh()->federation_likes_count)->toBe(6);
    });
});

describe('Announce Activity Handling', function (): void {
    it('increments federation_shares_count for valid Announce', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'testsub3',
            'display_name' => 'Test Sub 3',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);

        $post = App\Models\Post::factory()->create([
            'user_id' => $user->id,
            'sub_id' => $sub->id,
            'status' => 'published',
            'federation_shares_count' => 0,
        ]);

        $service = app(MultiActorActivityPubService::class);
        $domain = $service->getDomain();

        $result = $service->handleAnnounce([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Announce',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => "{$domain}/activitypub/notes/{$post->id}",
        ]);

        expect($result)->toBeTrue();
        expect($post->fresh()->federation_shares_count)->toBe(1);
    });

    it('ignores Announce for unknown post', function (): void {
        $service = app(MultiActorActivityPubService::class);

        $result = $service->handleAnnounce([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Announce',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => 'https://test.example.com/activitypub/notes/99999',
        ]);

        expect($result)->toBeFalse();
    });

    it('ignores Announce without actor', function (): void {
        $service = app(MultiActorActivityPubService::class);

        $result = $service->handleAnnounce([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Announce',
            'object' => 'https://test.example.com/activitypub/notes/1',
        ]);

        expect($result)->toBeFalse();
    });
});

describe('Create Activity Handling (Comments)', function (): void {
    it('creates comment from remote user', function (): void {
        Http::fake([
            'https://example.com/*' => Http::response([
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => 'https://example.com/users/alice',
                'type' => 'Person',
                'preferredUsername' => 'alice',
                'name' => 'Alice',
                'inbox' => 'https://example.com/users/alice/inbox',
                'outbox' => 'https://example.com/users/alice/outbox',
            ], 200, ['Content-Type' => 'application/activity+json']),
        ]);

        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'testsub4',
            'display_name' => 'Test Sub 4',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);

        $post = App\Models\Post::factory()->create([
            'user_id' => $user->id,
            'sub_id' => $sub->id,
            'status' => 'published',
            'federation_replies_count' => 0,
        ]);

        $service = app(MultiActorActivityPubService::class);
        $domain = $service->getDomain();

        $result = $service->handleCreate([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'actor' => 'https://example.com/users/alice',
            'object' => [
                'id' => 'https://example.com/notes/123',
                'type' => 'Note',
                'content' => '<p>This is a federated reply!</p>',
                'inReplyTo' => "{$domain}/activitypub/notes/{$post->id}",
            ],
        ]);

        expect($result)->toBeTrue();
        expect($post->fresh()->federation_replies_count)->toBe(1);

        // Check comment was created
        $comment = App\Models\Comment::where('post_id', $post->id)->first();
        expect($comment)->not->toBeNull();
        expect($comment->content)->toBe('This is a federated reply!');
        expect($comment->user_id)->toBeNull();
        expect($comment->remote_user_id)->not->toBeNull();
        expect($comment->source_uri)->toBe('https://example.com/notes/123');

        // Check remote user was created
        $remoteUser = App\Models\RemoteUser::find($comment->remote_user_id);
        expect($remoteUser)->not->toBeNull();
        expect($remoteUser->username)->toBe('alice');
        expect($remoteUser->domain)->toBe('example.com');
    });

    it('ignores Create without inReplyTo', function (): void {
        $service = app(MultiActorActivityPubService::class);

        $result = $service->handleCreate([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'id' => 'https://mastodon.social/notes/123',
                'type' => 'Note',
                'content' => '<p>This is a standalone post</p>',
            ],
        ]);

        expect($result)->toBeFalse();
    });

    it('ignores Create for non-Note objects', function (): void {
        $service = app(MultiActorActivityPubService::class);

        $result = $service->handleCreate([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'id' => 'https://mastodon.social/articles/123',
                'type' => 'Article',
                'content' => '<p>This is an article</p>',
                'inReplyTo' => 'https://test.example.com/activitypub/notes/1',
            ],
        ]);

        expect($result)->toBeFalse();
    });

    it('ignores Create for unknown parent post', function (): void {
        $service = app(MultiActorActivityPubService::class);

        $result = $service->handleCreate([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'id' => 'https://mastodon.social/notes/123',
                'type' => 'Note',
                'content' => '<p>Reply to unknown post</p>',
                'inReplyTo' => 'https://test.example.com/activitypub/notes/99999',
            ],
        ]);

        expect($result)->toBeFalse();
    });

    it('ignores Create with empty content', function (): void {
        Http::fake([
            'https://example.com/*' => Http::response([
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => 'https://example.com/users/alice',
                'type' => 'Person',
                'preferredUsername' => 'alice',
                'inbox' => 'https://example.com/users/alice/inbox',
            ], 200, ['Content-Type' => 'application/activity+json']),
        ]);

        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'testsub5',
            'display_name' => 'Test Sub 5',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);

        $post = App\Models\Post::factory()->create([
            'user_id' => $user->id,
            'sub_id' => $sub->id,
            'status' => 'published',
        ]);

        $service = app(MultiActorActivityPubService::class);
        $domain = $service->getDomain();

        $result = $service->handleCreate([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'actor' => 'https://example.com/users/alice',
            'object' => [
                'id' => 'https://example.com/notes/456',
                'type' => 'Note',
                'content' => '',
                'inReplyTo' => "{$domain}/activitypub/notes/{$post->id}",
            ],
        ]);

        expect($result)->toBeFalse();
    });
});

describe('Undo Like/Announce Handling', function (): void {
    it('decrements federation_likes_count on Undo Like', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'testsub6',
            'display_name' => 'Test Sub 6',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);

        $post = App\Models\Post::factory()->create([
            'user_id' => $user->id,
            'sub_id' => $sub->id,
            'status' => 'published',
            'federation_likes_count' => 10,
        ]);

        $service = app(MultiActorActivityPubService::class);
        $domain = $service->getDomain();

        $result = $service->handleUndoLikeOrAnnounce([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Undo',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'type' => 'Like',
                'actor' => 'https://mastodon.social/users/alice',
                'object' => "{$domain}/activitypub/notes/{$post->id}",
            ],
        ]);

        expect($result)->toBeTrue();
        expect($post->fresh()->federation_likes_count)->toBe(9);
    });

    it('decrements federation_shares_count on Undo Announce', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'testsub7',
            'display_name' => 'Test Sub 7',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);

        $post = App\Models\Post::factory()->create([
            'user_id' => $user->id,
            'sub_id' => $sub->id,
            'status' => 'published',
            'federation_shares_count' => 5,
        ]);

        $service = app(MultiActorActivityPubService::class);
        $domain = $service->getDomain();

        $result = $service->handleUndoLikeOrAnnounce([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Undo',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'type' => 'Announce',
                'actor' => 'https://mastodon.social/users/alice',
                'object' => "{$domain}/activitypub/notes/{$post->id}",
            ],
        ]);

        expect($result)->toBeTrue();
        expect($post->fresh()->federation_shares_count)->toBe(4);
    });

    it('does not decrement below zero', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'testsub8',
            'display_name' => 'Test Sub 8',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);

        $post = App\Models\Post::factory()->create([
            'user_id' => $user->id,
            'sub_id' => $sub->id,
            'status' => 'published',
            'federation_likes_count' => 0,
        ]);

        $service = app(MultiActorActivityPubService::class);
        $domain = $service->getDomain();

        $result = $service->handleUndoLikeOrAnnounce([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Undo',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'type' => 'Like',
                'actor' => 'https://mastodon.social/users/alice',
                'object' => "{$domain}/activitypub/notes/{$post->id}",
            ],
        ]);

        expect($result)->toBeFalse();
        expect($post->fresh()->federation_likes_count)->toBe(0);
    });

    it('ignores Undo for unknown post', function (): void {
        $service = app(MultiActorActivityPubService::class);

        $result = $service->handleUndoLikeOrAnnounce([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Undo',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'type' => 'Like',
                'actor' => 'https://mastodon.social/users/alice',
                'object' => 'https://test.example.com/activitypub/notes/99999',
            ],
        ]);

        expect($result)->toBeFalse();
    });

    it('handles Undo with string object URI', function (): void {
        $service = app(MultiActorActivityPubService::class);

        // When object is a string instead of array, it should return false
        $result = $service->handleUndoLikeOrAnnounce([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Undo',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => 'https://mastodon.social/activities/like-123',
        ]);

        expect($result)->toBeFalse();
    });
});
