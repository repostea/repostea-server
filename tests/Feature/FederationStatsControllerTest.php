<?php

declare(strict_types=1);

use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubBlockedInstance;
use App\Models\ActivityPubDeliveryLog;
use App\Models\Post;
use App\Models\RemoteUser;
use App\Models\Role;
use App\Models\Sub;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    // Create admin role if it doesn't exist (required for admin() factory state)
    Role::firstOrCreate(['slug' => 'admin'], ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Administrator role']);

    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();
});

afterEach(function (): void {
    ActivityPubBlockedInstance::query()->delete();
    ActivityPubActorFollower::query()->delete();
    ActivityPubDeliveryLog::query()->delete();
    ActivityPubActor::query()->delete();
    RemoteUser::query()->delete();
});

/**
 * Helper to create an ActivityPubActor with all required fields.
 */
function createActor(string $username, string $actorType, string $apType, ?int $entityId = null): ActivityPubActor
{
    $domain = config('app.url', 'https://test.local');

    return ActivityPubActor::create([
        'username' => $username,
        'preferred_username' => $username,
        'actor_type' => $actorType,
        'activitypub_type' => $apType,
        'entity_id' => $entityId,
        'actor_uri' => "{$domain}/activitypub/{$actorType}s/{$username}",
        'inbox_uri' => "{$domain}/activitypub/{$actorType}s/{$username}/inbox",
        'outbox_uri' => "{$domain}/activitypub/{$actorType}s/{$username}/outbox",
        'followers_uri' => "{$domain}/activitypub/{$actorType}s/{$username}/followers",
    ]);
}

/**
 * Helper to create an ActivityPubActorFollower with all required fields.
 */
function createFollower(ActivityPubActor $actor, string $followerUri): ActivityPubActorFollower
{
    $domain = parse_url($followerUri, \PHP_URL_HOST) ?? 'unknown';

    return ActivityPubActorFollower::create([
        'actor_id' => $actor->id,
        'follower_uri' => $followerUri,
        'follower_inbox' => "{$followerUri}/inbox",
        'follower_domain' => $domain,
        'followed_at' => now(),
    ]);
}

describe('FederationStatsController', function (): void {
    describe('index', function (): void {
        it('returns federation statistics dashboard data', function (): void {
            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats');

            $response->assertOk();
            $response->assertJsonStructure([
                'actors',
                'followers',
                'content',
                'blocked_instances',
                'deliveries',
                'recent_activity',
            ]);
        });

        it('returns actor statistics by type', function (): void {
            // Create actors of different types
            createActor('instance', ActivityPubActor::TYPE_INSTANCE, ActivityPubActor::AP_APPLICATION);
            createActor('testuser', ActivityPubActor::TYPE_USER, ActivityPubActor::AP_PERSON, $this->user->id);

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats');

            $response->assertOk();
            $response->assertJsonPath('actors.total', 2);
            $response->assertJsonPath('actors.by_type.instance', 1);
            $response->assertJsonPath('actors.by_type.users', 1);
        });

        it('returns blocked instance statistics', function (): void {
            ActivityPubBlockedInstance::blockDomain('spam.example.com', null, 'full');
            ActivityPubBlockedInstance::blockDomain('noisy.example.com', null, 'silence');

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats');

            $response->assertOk();
            $response->assertJsonPath('blocked_instances.total', 2);
            $response->assertJsonPath('blocked_instances.active', 2);
            $response->assertJsonPath('blocked_instances.by_type.full', 1);
            $response->assertJsonPath('blocked_instances.by_type.silence', 1);
        });

        it('denies access to non-admin users', function (): void {
            actingAs($this->user, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats');

            $response->assertForbidden();
        });

        it('denies access to unauthenticated users', function (): void {
            $response = getJson('/api/v1/admin/federation/stats');

            $response->assertUnauthorized();
        });
    });

    describe('engagedPosts', function (): void {
        it('returns posts with federation engagement', function (): void {
            $sub = Sub::create([
                'name' => 'test-sub',
                'display_name' => 'Test Sub',
                'created_by' => $this->user->id,
                'icon' => '💻',
                'color' => '#3B82F6',
            ]);

            // Create a post with federation engagement
            Post::factory()->create([
                'user_id' => $this->user->id,
                'sub_id' => $sub->id,
                'federation_likes_count' => 5,
                'federation_shares_count' => 3,
                'federation_replies_count' => 2,
            ]);

            // Create a post without federation engagement
            Post::factory()->create([
                'user_id' => $this->user->id,
                'sub_id' => $sub->id,
                'federation_likes_count' => 0,
                'federation_shares_count' => 0,
                'federation_replies_count' => 0,
            ]);

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats/engaged-posts');

            $response->assertOk();
            $response->assertJsonCount(1, 'posts');
            $response->assertJsonPath('posts.0.federation_stats.likes', 5);
            $response->assertJsonPath('posts.0.federation_stats.shares', 3);
            $response->assertJsonPath('posts.0.federation_stats.replies', 2);
        });

        it('orders posts by total engagement', function (): void {
            $sub = Sub::create([
                'name' => 'test-sub-2',
                'display_name' => 'Test Sub 2',
                'created_by' => $this->user->id,
                'icon' => '💻',
                'color' => '#3B82F6',
            ]);

            // Lower engagement
            Post::factory()->create([
                'user_id' => $this->user->id,
                'sub_id' => $sub->id,
                'federation_likes_count' => 1,
                'federation_shares_count' => 0,
                'federation_replies_count' => 0,
            ]);

            // Higher engagement
            Post::factory()->create([
                'user_id' => $this->user->id,
                'sub_id' => $sub->id,
                'federation_likes_count' => 10,
                'federation_shares_count' => 5,
                'federation_replies_count' => 3,
            ]);

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats/engaged-posts');

            $response->assertOk();
            // First post should have higher total engagement
            expect($response->json('posts.0.federation_stats.likes'))->toBe(10);
        });
    });

    describe('followersByInstance', function (): void {
        it('returns follower statistics grouped by instance', function (): void {
            $actor = createActor('instance', ActivityPubActor::TYPE_INSTANCE, ActivityPubActor::AP_APPLICATION);

            // Create followers from different instances
            createFollower($actor, 'https://mastodon.social/users/user1');
            createFollower($actor, 'https://mastodon.social/users/user2');
            createFollower($actor, 'https://lemmy.world/u/user3');

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats/instances');

            $response->assertOk();
            $response->assertJsonStructure([
                'instances' => [
                    '*' => [
                        'instance',
                        'follower_count',
                        'first_follow',
                        'last_follow',
                        'is_blocked',
                    ],
                ],
            ]);

            // mastodon.social should have 2 followers
            $mastodon = collect($response->json('instances'))->firstWhere('instance', 'mastodon.social');
            expect($mastodon['follower_count'])->toBe(2);
        });

        it('shows blocked status for instances', function (): void {
            $actor = createActor('instance', ActivityPubActor::TYPE_INSTANCE, ActivityPubActor::AP_APPLICATION);

            createFollower($actor, 'https://blocked.example.com/users/user1');

            ActivityPubBlockedInstance::blockDomain('blocked.example.com');

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats/instances');

            $response->assertOk();
            $blockedInstance = collect($response->json('instances'))->firstWhere('instance', 'blocked.example.com');
            expect($blockedInstance['is_blocked'])->toBeTrue();
        });
    });

    describe('deliveryStats', function (): void {
        it('returns delivery statistics for different time periods', function (): void {
            $actor = createActor('instance', ActivityPubActor::TYPE_INSTANCE, ActivityPubActor::AP_APPLICATION);

            // Create delivery logs
            ActivityPubDeliveryLog::logSuccess($actor->id, 'https://example.com/inbox', 'Create');
            ActivityPubDeliveryLog::logSuccess($actor->id, 'https://example.com/inbox', 'Announce');
            ActivityPubDeliveryLog::logFailure($actor->id, 'https://other.com/inbox', 'Like', 500, 'Server error', 1);

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats/deliveries');

            $response->assertOk();
            $response->assertJsonStructure([
                'last_24h',
                'last_7d',
                'all_time',
                'failures_by_instance',
            ]);

            expect($response->json('last_24h.total'))->toBe(3);
            expect($response->json('last_24h.success'))->toBe(2);
            expect($response->json('last_24h.failed'))->toBe(1);
        });
    });

    describe('recentFailures', function (): void {
        it('returns recent delivery failures', function (): void {
            $actor = createActor('instance', ActivityPubActor::TYPE_INSTANCE, ActivityPubActor::AP_APPLICATION);

            // Create some failures
            ActivityPubDeliveryLog::logFailure(
                $actor->id,
                'https://broken.example.com/inbox',
                'Create',
                500,
                'Internal Server Error',
                3,
            );

            ActivityPubDeliveryLog::logFailure(
                $actor->id,
                'https://timeout.example.com/inbox',
                'Announce',
                null,
                'Connection timeout',
                1,
            );

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats/failures');

            $response->assertOk();
            $response->assertJsonStructure([
                'failures' => [
                    '*' => [
                        'inbox_url',
                        'instance',
                        'activity_type',
                        'http_status',
                        'error_message',
                        'attempt_count',
                    ],
                ],
            ]);
            expect(count($response->json('failures')))->toBe(2);
        });

        it('limits failures to 50 by default', function (): void {
            $actor = createActor('instance', ActivityPubActor::TYPE_INSTANCE, ActivityPubActor::AP_APPLICATION);

            // Create many failures
            for ($i = 0; $i < 60; $i++) {
                ActivityPubDeliveryLog::logFailure(
                    $actor->id,
                    "https://instance{$i}.example.com/inbox",
                    'Create',
                    500,
                    'Error',
                    1,
                );
            }

            actingAs($this->admin, 'sanctum');
            $response = getJson('/api/v1/admin/federation/stats/failures');

            $response->assertOk();
            expect(count($response->json('failures')))->toBe(50);
        });
    });
});

describe('ActivityPubDeliveryLog Model', function (): void {
    it('extracts instance from inbox URL', function (): void {
        $actor = createActor('instance', ActivityPubActor::TYPE_INSTANCE, ActivityPubActor::AP_APPLICATION);

        $log = ActivityPubDeliveryLog::logSuccess(
            $actor->id,
            'https://mastodon.social/inbox',
            'Create',
        );

        expect($log->instance)->toBe('mastodon.social');
    });

    it('calculates success rate', function (): void {
        $actor = createActor('instance', ActivityPubActor::TYPE_INSTANCE, ActivityPubActor::AP_APPLICATION);

        // 8 successes, 2 failures = 80% success rate
        for ($i = 0; $i < 8; $i++) {
            ActivityPubDeliveryLog::logSuccess($actor->id, 'https://example.com/inbox', 'Create');
        }
        for ($i = 0; $i < 2; $i++) {
            ActivityPubDeliveryLog::logFailure($actor->id, 'https://other.com/inbox', 'Create', 500, 'Error', 1);
        }

        $stats = ActivityPubDeliveryLog::getStats(24);

        expect($stats['total'])->toBe(10);
        expect($stats['success'])->toBe(8);
        expect($stats['failed'])->toBe(2);
        expect($stats['success_rate'])->toBe(80.0);
    });
});
