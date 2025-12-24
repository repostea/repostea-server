<?php

declare(strict_types=1);

use App\Models\ActivityPubActor;
use App\Models\ActivityPubFollower;
use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use App\Services\ActivityPubService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = app(ActivityPubService::class);
    $this->user = User::factory()->create();
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
    config(['activitypub.actor.name' => 'Repostea']);
    config(['activitypub.actor.summary' => 'A content aggregation platform']);
    config(['app.client_url' => 'https://example.com']);
});

describe('Configuration', function (): void {
    test('isEnabled returns true when enabled', function (): void {
        config(['activitypub.enabled' => true]);
        expect($this->service->isEnabled())->toBeTrue();
    });

    test('isEnabled returns false when disabled', function (): void {
        config(['activitypub.enabled' => false]);
        expect($this->service->isEnabled())->toBeFalse();
    });

    test('getActorId returns correct URI', function (): void {
        expect($this->service->getActorId())->toBe('https://example.com/activitypub/actor');
    });

    test('getDomain returns domain without trailing slash', function (): void {
        config(['activitypub.domain' => 'https://example.com/']);
        expect($this->service->getDomain())->toBe('https://example.com');
    });

    test('getUsername returns configured username', function (): void {
        expect($this->service->getUsername())->toBe('repostea');
    });

    test('getClientUrl returns client URL', function (): void {
        expect($this->service->getClientUrl())->toBe('https://example.com');
    });

    test('getPublicDomain returns public domain', function (): void {
        config(['activitypub.public_domain' => 'https://public.example.com']);
        expect($this->service->getPublicDomain())->toBe('https://public.example.com');
    });

    test('getFollowerCount returns follower count', function (): void {
        expect($this->service->getFollowerCount())->toBe(0);

        ActivityPubFollower::create([
            'actor_id' => 'https://remote.example.com/users/alice',
            'inbox_url' => 'https://remote.example.com/users/alice/inbox',
            'username' => 'alice',
            'domain' => 'remote.example.com',
            'followed_at' => now(),
        ]);

        expect($this->service->getFollowerCount())->toBe(1);
    });
});

describe('Instance Actor', function (): void {
    test('getInstanceActor returns actor with keys', function (): void {
        $actor = $this->service->getInstanceActor();

        expect($actor)->toBeInstanceOf(ActivityPubActor::class);
        expect($actor->keys)->not->toBeNull();
        expect($actor->isInstance())->toBeTrue();
    });

    test('getKeyPair returns key pair', function (): void {
        $keyPair = $this->service->getKeyPair();

        expect($keyPair)->toHaveKeys(['private', 'public']);
        expect($keyPair['public'])->toContain('-----BEGIN PUBLIC KEY-----');
    });
});

describe('Actor Document', function (): void {
    test('buildActorDocument returns valid JSON-LD', function (): void {
        $document = $this->service->buildActorDocument();

        expect($document['@context'])->toContain('https://www.w3.org/ns/activitystreams');
        expect($document['type'])->toBe('Application');
        expect($document['preferredUsername'])->toBe('repostea');
        expect($document['name'])->toBe('Repostea');
        expect($document['inbox'])->toContain('/inbox');
        expect($document['outbox'])->toContain('/outbox');
        expect($document['publicKey'])->toBeArray();
        expect($document['publicKey']['publicKeyPem'])->toContain('-----BEGIN PUBLIC KEY-----');
    });

    test('buildActorDocument includes icon when configured', function (): void {
        config(['activitypub.actor.icon' => 'https://example.com/icon.png']);

        $document = $this->service->buildActorDocument();

        expect($document['icon'])->toBeArray();
        expect($document['icon']['type'])->toBe('Image');
        expect($document['icon']['url'])->toBe('https://example.com/icon.png');
    });
});

describe('WebFinger', function (): void {
    test('buildWebfingerResponse returns valid response', function (): void {
        $response = $this->service->buildWebfingerResponse();

        expect($response['subject'])->toBe('acct:repostea@example.com');
        expect($response['aliases'])->toContain($this->service->getActorId());
        expect($response['links'])->toBeArray();
        expect($response['links'][0]['rel'])->toBe('self');
        expect($response['links'][0]['type'])->toBe('application/activity+json');
    });
});

describe('HTTP Signatures', function (): void {
    test('signRequest adds required headers', function (): void {
        $headers = $this->service->signRequest(
            'POST',
            'https://remote.example.com/inbox',
            ['Content-Type' => 'application/activity+json'],
            '{"type":"Create"}',
        );

        expect($headers)->toHaveKeys(['Host', 'Date', 'Digest', 'Signature']);
        expect($headers['Host'])->toBe('remote.example.com');
        expect($headers['Digest'])->toStartWith('SHA-256=');
        expect($headers['Signature'])->toContain('keyId=');
        expect($headers['Signature'])->toContain('algorithm="rsa-sha256"');
        expect($headers['Signature'])->toContain('signature=');
    });

    test('signRequest works without body', function (): void {
        $headers = $this->service->signRequest(
            'GET',
            'https://remote.example.com/users/alice',
            ['Accept' => 'application/activity+json'],
        );

        expect($headers)->toHaveKeys(['Host', 'Date', 'Signature']);
        expect($headers)->not->toHaveKey('Digest');
    });
});

describe('Activity Building', function (): void {
    test('buildCreateActivity returns valid Create activity', function (): void {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'Test Post',
            'content' => 'This is test content',
            'slug' => 'test-post',
            'status' => 'published',
        ]);

        $activity = $this->service->buildCreateActivity($post);

        expect($activity['@context'])->toBe('https://www.w3.org/ns/activitystreams');
        expect($activity['type'])->toBe('Create');
        expect($activity['actor'])->toBe($this->service->getActorId());
        expect($activity['to'])->toContain('https://www.w3.org/ns/activitystreams#Public');
        expect($activity['object']['type'])->toBe('Note');
        expect($activity['object']['content'])->toContain('Test Post');
        expect($activity['object']['url'])->toContain('/posts/test-post');
    });

    test('buildCreateActivity includes thumbnail attachment', function (): void {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'Post with image',
            'slug' => 'post-with-image',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
            'status' => 'published',
        ]);

        $activity = $this->service->buildCreateActivity($post);

        expect($activity['object']['attachment'])->toBeArray();
        expect($activity['object']['attachment'][0]['type'])->toBe('Image');
        expect($activity['object']['attachment'][0]['url'])->toBe('https://example.com/thumb.jpg');
    });

    test('buildDeleteActivity returns valid Delete activity', function (): void {
        $activity = $this->service->buildDeleteActivity(123, 'test-slug');

        expect($activity['@context'])->toBe('https://www.w3.org/ns/activitystreams');
        expect($activity['type'])->toBe('Delete');
        expect($activity['actor'])->toBe($this->service->getActorId());
        expect($activity['to'])->toContain('https://www.w3.org/ns/activitystreams#Public');
        expect($activity['object']['type'])->toBe('Tombstone');
        expect($activity['object']['id'])->toContain('/activitypub/posts/123');
    });

    test('buildDeleteActorActivity returns valid actor Delete', function (): void {
        $activity = $this->service->buildDeleteActorActivity();

        expect($activity['type'])->toBe('Delete');
        expect($activity['actor'])->toBe($this->service->getActorId());
        expect($activity['object'])->toBe($this->service->getActorId());
    });

    test('buildLegacyDeleteActivity uses client URL format', function (): void {
        $activity = $this->service->buildLegacyDeleteActivity(123, 'old-post');

        expect($activity['type'])->toBe('Delete');
        expect($activity['object']['id'])->toContain('/posts/old-post');
        expect($activity['object']['id'])->not->toContain('/activitypub/');
    });

    test('buildNoteObject returns valid Note', function (): void {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'Note Test',
            'content' => 'Note content',
            'slug' => 'note-test',
            'status' => 'published',
        ]);

        $note = $this->service->buildNoteObject($post);

        expect($note['@context'])->toBe('https://www.w3.org/ns/activitystreams');
        expect($note['type'])->toBe('Note');
        expect($note['id'])->toContain('/activitypub/posts/');
        expect($note['attributedTo'])->toBe($this->service->getActorId());
        expect($note['content'])->toContain('Note Test');
    });
});

describe('Inbox Delivery', function (): void {
    test('sendToInbox returns false for invalid URL', function (): void {
        $result = $this->service->sendToInbox('http://localhost/inbox', ['type' => 'Create']);

        expect($result)->toBeFalse();
    });

    test('sendToInbox returns true on successful delivery', function (): void {
        Http::fake([
            'https://remote.example.com/*' => Http::response('', 202),
        ]);

        $result = $this->service->sendToInbox(
            'https://remote.example.com/inbox',
            ['type' => 'Create', 'actor' => $this->service->getActorId()],
        );

        expect($result)->toBeTrue();
    });

    test('sendToInbox returns false on failure', function (): void {
        Http::fake([
            'https://remote.example.com/*' => Http::response('Error', 500),
        ]);

        $result = $this->service->sendToInbox(
            'https://remote.example.com/inbox',
            ['type' => 'Create'],
        );

        expect($result)->toBeFalse();
    });
});

describe('Remote Actor Fetching', function (): void {
    test('fetchRemoteActor returns actor data', function (): void {
        Http::fake([
            'https://remote.example.com/*' => Http::response([
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => 'https://remote.example.com/users/alice',
                'type' => 'Person',
                'preferredUsername' => 'alice',
                'inbox' => 'https://remote.example.com/users/alice/inbox',
            ], 200, ['Content-Type' => 'application/activity+json']),
        ]);

        $actor = $this->service->fetchRemoteActor('https://remote.example.com/users/alice');

        expect($actor)->not->toBeNull();
        expect($actor['preferredUsername'])->toBe('alice');
        expect($actor['type'])->toBe('Person');
    });

    test('fetchRemoteActor returns null on failure', function (): void {
        Http::fake([
            'https://remote.example.com/*' => Http::response('Not Found', 404),
        ]);

        $actor = $this->service->fetchRemoteActor('https://remote.example.com/users/unknown');

        expect($actor)->toBeNull();
    });

    test('fetchRemoteActor rejects invalid URL', function (): void {
        $actor = $this->service->fetchRemoteActor('http://localhost/users/test');

        expect($actor)->toBeNull();
    });
});

describe('Follow Handling', function (): void {
    test('handleFollow creates follower', function (): void {
        Http::fake([
            'https://remote.example.com/*' => Http::response([
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => 'https://remote.example.com/users/alice',
                'type' => 'Person',
                'preferredUsername' => 'alice',
                'name' => 'Alice',
                'inbox' => 'https://remote.example.com/users/alice/inbox',
            ], 200),
        ]);

        $result = $this->service->handleFollow([
            'type' => 'Follow',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => $this->service->getActorId(),
        ]);

        expect($result)->toBeTrue();
        expect(ActivityPubFollower::count())->toBe(1);

        $follower = ActivityPubFollower::first();
        expect($follower->username)->toBe('alice');
        expect($follower->domain)->toBe('remote.example.com');
    });

    test('handleFollow rejects empty actor', function (): void {
        $result = $this->service->handleFollow([
            'type' => 'Follow',
            'actor' => '',
        ]);

        expect($result)->toBeFalse();
    });

    test('handleFollow rejects missing actor', function (): void {
        $result = $this->service->handleFollow([
            'type' => 'Follow',
        ]);

        expect($result)->toBeFalse();
    });
});

describe('Undo Handling', function (): void {
    test('handleUndo removes follower', function (): void {
        ActivityPubFollower::create([
            'actor_id' => 'https://remote.example.com/users/alice',
            'inbox_url' => 'https://remote.example.com/users/alice/inbox',
            'username' => 'alice',
            'domain' => 'remote.example.com',
            'followed_at' => now(),
        ]);

        $result = $this->service->handleUndo([
            'type' => 'Undo',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => [
                'type' => 'Follow',
                'actor' => 'https://remote.example.com/users/alice',
            ],
        ]);

        expect($result)->toBeTrue();
        expect(ActivityPubFollower::count())->toBe(0);
    });

    test('handleUndo ignores non-Follow objects', function (): void {
        $result = $this->service->handleUndo([
            'type' => 'Undo',
            'actor' => 'https://remote.example.com/users/alice',
            'object' => [
                'type' => 'Like',
            ],
        ]);

        expect($result)->toBeFalse();
    });
});
