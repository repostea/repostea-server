<?php

declare(strict_types=1);

use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubBlockedInstance;
use App\Models\ActivityPubDeliveryLog;
use App\Models\Role;
use App\Models\User;

beforeEach(function (): void {
    // Create roles
    Role::create([
        'name' => 'admin',
        'slug' => 'admin',
        'display_name' => 'Administrator',
        'description' => 'Administrator role for testing',
    ]);

    Role::create([
        'name' => 'moderator',
        'slug' => 'moderator',
        'display_name' => 'Moderator',
        'description' => 'Moderator role for testing',
    ]);

    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();
});

describe('Blocked Instances Page', function (): void {
    test('non-authenticated users cannot access blocked instances page', function (): void {
        $response = $this->get(route('admin.federation.blocked'));

        $response->assertRedirect(route('admin.login'));
    });

    test('non-admin users cannot access blocked instances page', function (): void {
        $response = $this->actingAs($this->user)->get(route('admin.federation.blocked'));

        $response->assertForbidden();
    });

    test('admin can view blocked instances page', function (): void {
        $response = $this->actingAs($this->admin)->get(route('admin.federation.blocked'));

        $response->assertOk();
        $response->assertViewIs('admin.activitypub.blocked');
    });

    test('blocked instances page displays stats', function (): void {
        ActivityPubBlockedInstance::blockDomain('spam.example', 'Spam', 'full');
        ActivityPubBlockedInstance::blockDomain('silence.example', 'Unwanted', 'silence');

        $response = $this->actingAs($this->admin)->get(route('admin.federation.blocked'));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total'] === 2
                && $stats['active'] === 2
                && $stats['full_blocks'] === 1
                && $stats['silenced'] === 1);
    });

    test('blocked instances page lists blocked instances', function (): void {
        ActivityPubBlockedInstance::blockDomain('blocked.example', 'Test reason', 'full');

        $response = $this->actingAs($this->admin)->get(route('admin.federation.blocked'));

        $response->assertOk();
        $response->assertViewHas('blockedInstances');
        $response->assertSee('blocked.example');
    });

    test('blocked instances can be filtered by status', function (): void {
        $active = ActivityPubBlockedInstance::blockDomain('active.example', 'Active', 'full');
        $inactive = ActivityPubBlockedInstance::blockDomain('inactive.example', 'Inactive', 'full');
        $inactive->update(['is_active' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.federation.blocked', ['status' => 'active']));

        $response->assertOk();
        $response->assertSee('active.example');
        $response->assertDontSee('inactive.example');
    });

    test('blocked instances can be filtered by block type', function (): void {
        ActivityPubBlockedInstance::blockDomain('full.example', 'Full block', 'full');
        ActivityPubBlockedInstance::blockDomain('silence.example', 'Silenced', 'silence');

        $response = $this->actingAs($this->admin)->get(route('admin.federation.blocked', ['block_type' => 'silence']));

        $response->assertOk();
        $response->assertSee('silence.example');
        $response->assertDontSee('full.example');
    });

    test('blocked instances can be searched', function (): void {
        ActivityPubBlockedInstance::blockDomain('mastodon.social', 'Spam', 'full');
        ActivityPubBlockedInstance::blockDomain('other.example', 'Other', 'full');

        $response = $this->actingAs($this->admin)->get(route('admin.federation.blocked', ['search' => 'mastodon']));

        $response->assertOk();
        $response->assertSee('mastodon.social');
        $response->assertDontSee('other.example');
    });
});

describe('Store Blocked Instance', function (): void {
    test('admin can block a new instance', function (): void {
        $response = $this->actingAs($this->admin)->post(route('admin.federation.blocked.store'), [
            'domain' => 'spam.example',
            'reason' => 'Spam content',
            'block_type' => 'full',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('activity_pub_blocked_instances', [
            'domain' => 'spam.example',
            'reason' => 'Spam content',
            'block_type' => 'full',
            'is_active' => true,
        ]);
    });

    test('admin can block instance with URL format', function (): void {
        $response = $this->actingAs($this->admin)->post(route('admin.federation.blocked.store'), [
            'domain' => 'https://mastodon.social/users/test',
            'reason' => 'Test',
            'block_type' => 'full',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('activity_pub_blocked_instances', [
            'domain' => 'mastodon.social',
        ]);
    });

    test('admin can create silence block', function (): void {
        $response = $this->actingAs($this->admin)->post(route('admin.federation.blocked.store'), [
            'domain' => 'noisy.example',
            'reason' => 'Too much noise',
            'block_type' => 'silence',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('activity_pub_blocked_instances', [
            'domain' => 'noisy.example',
            'block_type' => 'silence',
        ]);
    });

    test('cannot block already blocked domain', function (): void {
        ActivityPubBlockedInstance::blockDomain('existing.example', 'Existing', 'full');

        $response = $this->actingAs($this->admin)->post(route('admin.federation.blocked.store'), [
            'domain' => 'existing.example',
            'reason' => 'Duplicate',
            'block_type' => 'full',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        expect(ActivityPubBlockedInstance::where('domain', 'existing.example')->count())->toBe(1);
    });

    test('non-admin cannot block instances', function (): void {
        $response = $this->actingAs($this->user)->post(route('admin.federation.blocked.store'), [
            'domain' => 'test.example',
            'block_type' => 'full',
        ]);

        $response->assertForbidden();
    });

    test('domain is required', function (): void {
        $response = $this->actingAs($this->admin)->post(route('admin.federation.blocked.store'), [
            'block_type' => 'full',
        ]);

        $response->assertSessionHasErrors('domain');
    });

    test('block_type must be valid', function (): void {
        $response = $this->actingAs($this->admin)->post(route('admin.federation.blocked.store'), [
            'domain' => 'test.example',
            'block_type' => 'invalid',
        ]);

        $response->assertSessionHasErrors('block_type');
    });
});

describe('Update Blocked Instance', function (): void {
    test('admin can update block reason', function (): void {
        $block = ActivityPubBlockedInstance::blockDomain('test.example', 'Old reason', 'full');

        $response = $this->actingAs($this->admin)->put(route('admin.federation.blocked.update', $block), [
            'reason' => 'New reason',
            'block_type' => 'full',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $block->refresh();
        expect($block->reason)->toBe('New reason');
    });

    test('admin can change block type', function (): void {
        $block = ActivityPubBlockedInstance::blockDomain('test.example', 'Test', 'full');

        $response = $this->actingAs($this->admin)->put(route('admin.federation.blocked.update', $block), [
            'reason' => 'Test',
            'block_type' => 'silence',
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $block->refresh();
        expect($block->block_type)->toBe('silence');
    });

    test('admin can deactivate block', function (): void {
        $block = ActivityPubBlockedInstance::blockDomain('test.example', 'Test', 'full');

        $response = $this->actingAs($this->admin)->put(route('admin.federation.blocked.update', $block), [
            'reason' => 'Test',
            'block_type' => 'full',
            'is_active' => false,
        ]);

        $response->assertRedirect();

        $block->refresh();
        expect($block->is_active)->toBeFalse();
    });

    test('non-admin cannot update blocks', function (): void {
        $block = ActivityPubBlockedInstance::blockDomain('test.example', 'Test', 'full');

        $response = $this->actingAs($this->user)->put(route('admin.federation.blocked.update', $block), [
            'reason' => 'Hacked',
            'block_type' => 'full',
            'is_active' => true,
        ]);

        $response->assertForbidden();
    });
});

describe('Delete Blocked Instance', function (): void {
    test('admin can delete block', function (): void {
        $block = ActivityPubBlockedInstance::blockDomain('test.example', 'Test', 'full');

        $response = $this->actingAs($this->admin)->delete(route('admin.federation.blocked.destroy', $block));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('activity_pub_blocked_instances', [
            'domain' => 'test.example',
        ]);
    });

    test('non-admin cannot delete blocks', function (): void {
        $block = ActivityPubBlockedInstance::blockDomain('test.example', 'Test', 'full');

        $response = $this->actingAs($this->user)->delete(route('admin.federation.blocked.destroy', $block));

        $response->assertForbidden();

        $this->assertDatabaseHas('activity_pub_blocked_instances', [
            'domain' => 'test.example',
        ]);
    });
});

describe('Statistics Page', function (): void {
    test('non-authenticated users cannot access statistics page', function (): void {
        $response = $this->get(route('admin.federation.stats'));

        $response->assertRedirect(route('admin.login'));
    });

    test('non-admin users cannot access statistics page', function (): void {
        $response = $this->actingAs($this->user)->get(route('admin.federation.stats'));

        $response->assertForbidden();
    });

    test('admin can view statistics page', function (): void {
        $response = $this->actingAs($this->admin)->get(route('admin.federation.stats'));

        $response->assertOk();
        $response->assertViewIs('admin.activitypub.stats');
    });

    test('statistics page displays actor stats', function (): void {
        // Create test actors
        createTestActorForAdmin('instance', ActivityPubActor::TYPE_INSTANCE, 'Application');
        createTestActorForAdmin('testuser', ActivityPubActor::TYPE_USER, 'Person');
        createTestActorForAdmin('testgroup', ActivityPubActor::TYPE_GROUP, 'Group');

        $response = $this->actingAs($this->admin)->get(route('admin.federation.stats'));

        $response->assertOk();
        $response->assertViewHas('actorStats', fn ($stats) => $stats['total'] === 3
                && $stats['instance'] === 1
                && $stats['users'] === 1
                && $stats['groups'] === 1);
    });

    test('statistics page displays follower stats', function (): void {
        $actor = createTestActorForAdmin('testactor', ActivityPubActor::TYPE_INSTANCE, 'Application');

        ActivityPubActorFollower::create([
            'actor_id' => $actor->id,
            'follower_uri' => 'https://mastodon.social/users/alice',
            'follower_inbox' => 'https://mastodon.social/users/alice/inbox',
            'follower_domain' => 'mastodon.social',
            'followed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.federation.stats'));

        $response->assertOk();
        $response->assertViewHas('followerStats', fn ($stats) => $stats['total'] === 1);
    });

    test('statistics page displays blocked stats', function (): void {
        ActivityPubBlockedInstance::blockDomain('full.example', 'Full', 'full');
        ActivityPubBlockedInstance::blockDomain('silence.example', 'Silence', 'silence');

        $response = $this->actingAs($this->admin)->get(route('admin.federation.stats'));

        $response->assertOk();
        $response->assertViewHas('blockedStats', fn ($stats) => $stats['total'] === 2
                && $stats['active'] === 2
                && $stats['full'] === 1
                && $stats['silence'] === 1);
    });

    test('statistics page displays delivery stats', function (): void {
        $actor = createTestActorForAdmin('delivery', ActivityPubActor::TYPE_INSTANCE, 'Application');

        // Create delivery logs using static methods
        ActivityPubDeliveryLog::logSuccess(
            $actor->id,
            'https://example.com/inbox',
            'Create',
            202,
        );

        ActivityPubDeliveryLog::logFailure(
            $actor->id,
            'https://failed.example/inbox',
            'Create',
            500,
            'Server error',
        );

        $response = $this->actingAs($this->admin)->get(route('admin.federation.stats'));

        $response->assertOk();
        $response->assertViewHas('deliveryStats');
    });

    test('statistics page displays top instances', function (): void {
        $actor = createTestActorForAdmin('popular', ActivityPubActor::TYPE_INSTANCE, 'Application');

        // Create followers from different instances
        ActivityPubActorFollower::create([
            'actor_id' => $actor->id,
            'follower_uri' => 'https://mastodon.social/users/user1',
            'follower_inbox' => 'https://mastodon.social/users/user1/inbox',
            'follower_domain' => 'mastodon.social',
            'followed_at' => now(),
        ]);

        ActivityPubActorFollower::create([
            'actor_id' => $actor->id,
            'follower_uri' => 'https://mastodon.social/users/user2',
            'follower_inbox' => 'https://mastodon.social/users/user2/inbox',
            'follower_domain' => 'mastodon.social',
            'followed_at' => now(),
        ]);

        ActivityPubActorFollower::create([
            'actor_id' => $actor->id,
            'follower_uri' => 'https://other.social/users/user1',
            'follower_inbox' => 'https://other.social/users/user1/inbox',
            'follower_domain' => 'other.social',
            'followed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.federation.stats'));

        $response->assertOk();
        $response->assertViewHas('topInstances', fn ($instances) => $instances->first()->instance === 'mastodon.social'
                && $instances->first()->count === 2);
    });

    test('statistics page displays recent failures', function (): void {
        $actor = createTestActorForAdmin('failing', ActivityPubActor::TYPE_INSTANCE, 'Application');

        ActivityPubDeliveryLog::logFailure(
            $actor->id,
            'https://down.example/inbox',
            'Create',
            503,
            'Service unavailable',
            3,
        );

        $response = $this->actingAs($this->admin)->get(route('admin.federation.stats'));

        $response->assertOk();
        $response->assertViewHas('recentFailures');
    });
});

// Helper function to create actors (namespaced to avoid conflicts)
function createTestActorForAdmin(string $username, string $actorType, string $apType, ?int $entityId = null): ActivityPubActor
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
        'public_key' => 'test-public-key',
        'private_key' => 'test-private-key',
        'is_active' => true,
    ]);
}
