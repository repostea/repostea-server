<?php

declare(strict_types=1);

use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubSubSettings;
use App\Models\ActivityPubUserSettings;
use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use App\Services\MultiActorActivityPubService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = app(MultiActorActivityPubService::class);
    $this->user = User::factory()->create(['username' => 'testuser']);
    $this->sub = Sub::create([
        'name' => 'testsub',
        'display_name' => 'Test Sub',
        'created_by' => $this->user->id,
        'color' => '#000000',
    ]);

    config(['activitypub.enabled' => true]);
    config(['activitypub.domain' => 'https://example.com']);
    config(['activitypub.public_domain' => 'https://example.com']);
    config(['activitypub.actor.username' => 'repostea']);
    config(['app.client_url' => 'https://example.com']);
});

describe('Domain Configuration', function (): void {
    test('getDomain returns domain without trailing slash', function (): void {
        config(['activitypub.domain' => 'https://example.com/']);
        expect($this->service->getDomain())->toBe('https://example.com');
    });

    test('getPublicDomain returns public domain', function (): void {
        config(['activitypub.public_domain' => 'https://public.example.com']);
        expect($this->service->getPublicDomain())->toBe('https://public.example.com');
    });

    test('getPublicHost returns host only', function (): void {
        expect($this->service->getPublicHost())->toBe('example.com');
    });
});

describe('Actor Management', function (): void {
    test('getInstanceActor returns instance actor with keys', function (): void {
        $actor = $this->service->getInstanceActor();
        $actor->load('keys');

        expect($actor)->toBeInstanceOf(ActivityPubActor::class);
        expect($actor->isInstance())->toBeTrue();
        expect($actor->keys)->not->toBeNull();
    });

    test('getUserActor returns null when federation disabled', function (): void {
        $actor = $this->service->getUserActor($this->user);

        expect($actor)->toBeNull();
    });

    test('getUserActor returns actor when federation enabled', function (): void {
        ActivityPubUserSettings::create([
            'user_id' => $this->user->id,
            'federation_enabled' => true,
        ]);

        $actor = $this->service->getUserActor($this->user);

        expect($actor)->not->toBeNull();
        expect($actor->username)->toBe('testuser');
        expect($actor->isUser())->toBeTrue();
    });

    test('getGroupActor returns null when federation disabled', function (): void {
        $actor = $this->service->getGroupActor($this->sub);

        expect($actor)->toBeNull();
    });

    test('getGroupActor returns actor when federation enabled', function (): void {
        ActivityPubSubSettings::create([
            'sub_id' => $this->sub->id,
            'federation_enabled' => true,
        ]);

        $actor = $this->service->getGroupActor($this->sub);

        expect($actor)->not->toBeNull();
        expect($actor->username)->toBe('testsub');
        expect($actor->isGroup())->toBeTrue();
    });

    test('enableUserFederation creates actor and settings', function (): void {
        $actor = $this->service->enableUserFederation($this->user);

        expect($actor)->toBeInstanceOf(ActivityPubActor::class);
        expect($actor->username)->toBe('testuser');

        $settings = ActivityPubUserSettings::where('user_id', $this->user->id)->first();
        expect($settings->federation_enabled)->toBeTrue();
    });

    test('enableSubFederation creates actor and settings', function (): void {
        $actor = $this->service->enableSubFederation($this->sub);

        expect($actor)->toBeInstanceOf(ActivityPubActor::class);
        expect($actor->username)->toBe('testsub');
        expect($actor->isGroup())->toBeTrue();

        $settings = ActivityPubSubSettings::where('sub_id', $this->sub->id)->first();
        expect($settings->federation_enabled)->toBeTrue();
    });
});

describe('WebFinger', function (): void {
    test('buildWebfingerResponse for user actor', function (): void {
        $actor = ActivityPubActor::findOrCreateForUser($this->user);

        $response = $this->service->buildWebfingerResponse($actor);

        expect($response['subject'])->toBe('acct:testuser@example.com');
        expect($response['links'][0]['href'])->toBe($actor->actor_uri);
    });

    test('buildWebfingerResponse for group actor', function (): void {
        $actor = ActivityPubActor::findOrCreateForSub($this->sub);

        $response = $this->service->buildWebfingerResponse($actor);

        expect($response['subject'])->toBe('acct:!testsub@example.com');
    });

    test('resolveWebfinger returns null for invalid resource', function (): void {
        expect($this->service->resolveWebfinger('invalid'))->toBeNull();
    });

    test('resolveWebfinger returns null for wrong domain', function (): void {
        expect($this->service->resolveWebfinger('acct:user@other.com'))->toBeNull();
    });

    test('resolveWebfinger returns null for non-existent user', function (): void {
        expect($this->service->resolveWebfinger('acct:unknown@example.com'))->toBeNull();
    });

    test('resolveWebfinger finds user by username', function (): void {
        ActivityPubActor::findOrCreateForUser($this->user);

        $actor = $this->service->resolveWebfinger('acct:testuser@example.com');

        expect($actor)->not->toBeNull();
        expect($actor->username)->toBe('testuser');
    });

    test('resolveWebfinger finds group with bang prefix', function (): void {
        ActivityPubActor::findOrCreateForSub($this->sub);

        $actor = $this->service->resolveWebfinger('acct:!testsub@example.com');

        expect($actor)->not->toBeNull();
        expect($actor->isGroup())->toBeTrue();
    });

    test('resolveWebfinger finds instance actor', function (): void {
        $this->service->getInstanceActor();

        $actor = $this->service->resolveWebfinger('acct:repostea@example.com');

        expect($actor)->not->toBeNull();
        expect($actor->isInstance())->toBeTrue();
    });
});

describe('HTTP Signatures', function (): void {
    test('signRequest adds signature headers', function (): void {
        $actor = $this->service->enableUserFederation($this->user);
        $actor->load('keys');

        $headers = $this->service->signRequest(
            $actor,
            'POST',
            'https://remote.example.com/inbox',
            ['Content-Type' => 'application/activity+json'],
            '{"type":"Create"}',
        );

        expect($headers)->toHaveKeys(['Host', 'Date', 'Digest', 'Signature']);
        expect($headers['Signature'])->toContain('keyId=');
    });

    test('signRequest throws for actor without keys', function (): void {
        $actor = ActivityPubActor::create([
            'actor_type' => ActivityPubActor::TYPE_USER,
            'activitypub_type' => ActivityPubActor::AP_PERSON,
            'username' => 'nokeys',
            'preferred_username' => 'nokeys',
            'actor_uri' => 'https://example.com/users/nokeys',
            'inbox_uri' => 'https://example.com/users/nokeys/inbox',
            'outbox_uri' => 'https://example.com/users/nokeys/outbox',
            'followers_uri' => 'https://example.com/users/nokeys/followers',
        ]);

        expect(fn () => $this->service->signRequest(
            $actor,
            'POST',
            'https://remote.example.com/inbox',
            [],
        ))->toThrow(InvalidArgumentException::class);
    });
});

describe('Activity Building', function (): void {
    test('buildCreateActivity returns valid Create', function (): void {
        $actor = $this->service->enableUserFederation($this->user);
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'Test Post',
            'slug' => 'test-post',
            'status' => 'published',
        ]);

        $activity = $this->service->buildCreateActivity($actor, $post);

        expect($activity['type'])->toBe('Create');
        expect($activity['actor'])->toBe($actor->actor_uri);
        expect($activity['object']['type'])->toBe('Note');
        expect($activity['object']['content'])->toContain('Test Post');
    });

    test('buildAnnounceActivity returns FEP-1b12 compatible Announce', function (): void {
        $userActor = $this->service->enableUserFederation($this->user);
        $groupActor = $this->service->enableSubFederation($this->sub);
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'Announced Post',
            'slug' => 'announced-post',
            'status' => 'published',
        ]);

        $activity = $this->service->buildAnnounceActivity($groupActor, $userActor, $post);

        expect($activity['type'])->toBe('Announce');
        expect($activity['actor'])->toBe($groupActor->actor_uri);
        expect($activity['audience'])->toBe($groupActor->actor_uri);
        expect($activity['object']['type'])->toBe('Create');
    });

    test('buildUpdateActivity includes updated timestamp', function (): void {
        $actor = $this->service->enableUserFederation($this->user);
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'Updated Post',
            'slug' => 'updated-post',
            'status' => 'published',
        ]);

        $activity = $this->service->buildUpdateActivity($actor, $post);

        expect($activity['type'])->toBe('Update');
        expect($activity['object']['updated'])->not->toBeNull();
    });

    test('buildDeleteActivity returns Tombstone', function (): void {
        $actor = $this->service->enableUserFederation($this->user);

        $activity = $this->service->buildDeleteActivity($actor, 123);

        expect($activity['type'])->toBe('Delete');
        expect($activity['actor'])->toBe($actor->actor_uri);
        expect($activity['object']['type'])->toBe('Tombstone');
        expect($activity['object']['id'])->toContain('/notes/123');
    });
});

describe('Inbox Delivery', function (): void {
    test('sendToInbox rejects invalid URL', function (): void {
        $actor = $this->service->enableUserFederation($this->user);
        $actor->load('keys');

        $result = $this->service->sendToInbox(
            $actor,
            'http://localhost/inbox',
            ['type' => 'Create'],
        );

        expect($result)->toBeFalse();
    });

    test('sendToInbox rejects blocked instance', function (): void {
        App\Models\ActivityPubBlockedInstance::create([
            'domain' => 'blocked.example.com',
            'reason' => 'Test block',
            'blocked_by' => $this->user->id,
        ]);

        $actor = $this->service->enableUserFederation($this->user);
        $actor->load('keys');

        $result = $this->service->sendToInbox(
            $actor,
            'https://blocked.example.com/inbox',
            ['type' => 'Create'],
        );

        expect($result)->toBeFalse();
    });

    test('sendToInbox succeeds with valid URL', function (): void {
        Http::fake([
            'https://remote.example.com/*' => Http::response('', 202),
        ]);

        $actor = $this->service->enableUserFederation($this->user);
        $actor->load('keys');

        $result = $this->service->sendToInbox(
            $actor,
            'https://remote.example.com/inbox',
            ['type' => 'Create'],
        );

        expect($result)->toBeTrue();
    });
});

describe('Follow Handling', function (): void {
    test('handleFollow creates follower record', function (): void {
        Http::fake([
            'https://remote.example.com/*' => Http::response([
                'id' => 'https://remote.example.com/users/alice',
                'type' => 'Person',
                'preferredUsername' => 'alice',
                'inbox' => 'https://remote.example.com/users/alice/inbox',
            ], 200),
        ]);

        $actor = $this->service->enableUserFederation($this->user);
        $actor->load('keys');

        $result = $this->service->handleFollow($actor, [
            'type' => 'Follow',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => $actor->actor_uri,
        ]);

        expect($result)->toBeTrue();
        expect(ActivityPubActorFollower::count())->toBe(1);
    });

    test('handleFollow rejects empty actor', function (): void {
        $actor = $this->service->enableUserFederation($this->user);

        $result = $this->service->handleFollow($actor, [
            'type' => 'Follow',
            'actor' => '',
        ]);

        expect($result)->toBeFalse();
    });

    test('handleUndo removes follower', function (): void {
        $actor = $this->service->enableUserFederation($this->user);

        ActivityPubActorFollower::create([
            'actor_id' => $actor->id,
            'follower_uri' => 'https://remote.example.com/users/alice',
            'follower_inbox' => 'https://remote.example.com/users/alice/inbox',
            'follower_username' => 'alice',
            'follower_domain' => 'remote.example.com',
            'followed_at' => now(),
        ]);

        $result = $this->service->handleUndo($actor, [
            'type' => 'Undo',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => [
                'type' => 'Follow',
            ],
        ]);

        expect($result)->toBeTrue();
        expect(ActivityPubActorFollower::count())->toBe(0);
    });
});

describe('Remote Actor Fetching', function (): void {
    test('fetchRemoteActor returns actor data', function (): void {
        Http::fake([
            'https://remote.example.com/*' => Http::response([
                'id' => 'https://remote.example.com/users/bob',
                'type' => 'Person',
                'preferredUsername' => 'bob',
            ], 200),
        ]);

        $actor = $this->service->fetchRemoteActor('https://remote.example.com/users/bob');

        expect($actor)->not->toBeNull();
        expect($actor['preferredUsername'])->toBe('bob');
    });

    test('fetchRemoteActor rejects invalid URL', function (): void {
        $actor = $this->service->fetchRemoteActor('http://localhost/users/test');

        expect($actor)->toBeNull();
    });

    test('fetchRemoteActor returns null on failure', function (): void {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        $actor = $this->service->fetchRemoteActor('https://remote.example.com/users/unknown');

        expect($actor)->toBeNull();
    });
});
