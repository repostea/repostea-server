<?php

declare(strict_types=1);

use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorKey;
use App\Models\ActivityPubPostSettings;
use App\Models\ActivityPubUserSettings;
use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use App\Services\MultiActorActivityPubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['activitypub.enabled' => true]);
    config(['activitypub.domain' => 'https://test.example.com']);
    config(['activitypub.public_domain' => 'https://test.example.com']);
    config(['activitypub.actor.username' => 'testinstance']);
});

describe('ActivityPubActor', function (): void {
    it('creates instance actor', function (): void {
        $actor = ActivityPubActor::findOrCreateInstanceActor();

        expect($actor)->toBeInstanceOf(ActivityPubActor::class)
            ->and($actor->actor_type)->toBe(ActivityPubActor::TYPE_INSTANCE)
            ->and($actor->activitypub_type)->toBe(ActivityPubActor::AP_APPLICATION)
            ->and($actor->username)->toBe('testinstance')
            ->and($actor->isInstance())->toBeTrue();
    });

    it('creates user actor', function (): void {
        $user = User::factory()->create(['username' => 'testuser']);

        $actor = ActivityPubActor::findOrCreateForUser($user);

        expect($actor)->toBeInstanceOf(ActivityPubActor::class)
            ->and($actor->actor_type)->toBe(ActivityPubActor::TYPE_USER)
            ->and($actor->activitypub_type)->toBe(ActivityPubActor::AP_PERSON)
            ->and($actor->username)->toBe('testuser')
            ->and($actor->isUser())->toBeTrue()
            ->and($actor->actor_uri)->toBe('https://test.example.com/activitypub/users/testuser');
    });

    it('creates group actor for sub', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'technology',
            'display_name' => 'Technology',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);

        $actor = ActivityPubActor::findOrCreateForSub($sub);

        expect($actor)->toBeInstanceOf(ActivityPubActor::class)
            ->and($actor->actor_type)->toBe(ActivityPubActor::TYPE_GROUP)
            ->and($actor->activitypub_type)->toBe(ActivityPubActor::AP_GROUP)
            ->and($actor->username)->toBe('technology')
            ->and($actor->isGroup())->toBeTrue()
            ->and($actor->actor_uri)->toBe('https://test.example.com/activitypub/groups/technology');
    });

    it('generates correct handles', function (): void {
        $user = User::factory()->create(['username' => 'juan']);
        $creator = User::factory()->create();
        $sub = Sub::create([
            'name' => 'tech',
            'display_name' => 'Tech',
            'created_by' => $creator->id,
            'color' => '#000000',
        ]);

        $userActor = ActivityPubActor::findOrCreateForUser($user);
        $groupActor = ActivityPubActor::findOrCreateForSub($sub);

        expect($userActor->getHandle())->toBe('@juan@test.example.com')
            ->and($groupActor->getHandle())->toBe('!tech@test.example.com');
    });

    it('generates webfinger resource', function (): void {
        $user = User::factory()->create(['username' => 'alice']);
        $creator = User::factory()->create();
        $sub = Sub::create([
            'name' => 'gaming',
            'display_name' => 'Gaming',
            'created_by' => $creator->id,
            'color' => '#000000',
        ]);

        $userActor = ActivityPubActor::findOrCreateForUser($user);
        $groupActor = ActivityPubActor::findOrCreateForSub($sub);

        expect($userActor->getWebfingerResource())->toBe('acct:alice@test.example.com')
            ->and($groupActor->getWebfingerResource())->toBe('acct:!gaming@test.example.com');
    });
});

describe('ActivityPubActorKey', function (): void {
    it('generates keys for actor', function (): void {
        $actor = ActivityPubActor::findOrCreateInstanceActor();
        $keys = ActivityPubActorKey::generateForActor($actor);

        expect($keys)->toBeInstanceOf(ActivityPubActorKey::class)
            ->and($keys->public_key)->toContain('-----BEGIN PUBLIC KEY-----')
            ->and($keys->key_id)->toBe($actor->actor_uri . '#main-key');
    });

    it('ensures keys exist', function (): void {
        $actor = ActivityPubActor::findOrCreateInstanceActor();

        $keys1 = ActivityPubActorKey::ensureForActor($actor);
        $actor->refresh();
        $keys2 = ActivityPubActorKey::ensureForActor($actor);

        expect($keys1->id)->toBe($keys2->id);
    });
});

describe('ActivityPubUserSettings', function (): void {
    it('creates default settings for user', function (): void {
        $user = User::factory()->create();

        $settings = ActivityPubUserSettings::getOrCreate($user);

        expect($settings->federation_enabled)->toBeFalse()
            ->and($settings->default_federate_posts)->toBeFalse()
            ->and($settings->indexable)->toBeTrue();
    });

    it('enables federation', function (): void {
        $user = User::factory()->create();
        $settings = ActivityPubUserSettings::getOrCreate($user);

        $settings->enableFederation();

        expect($settings->federation_enabled)->toBeTrue()
            ->and($settings->federation_enabled_at)->not->toBeNull();

        // Actor should be created
        $actor = ActivityPubActor::findByUsername($user->username, ActivityPubActor::TYPE_USER);
        expect($actor)->not->toBeNull();
    });
});

describe('ActivityPubPostSettings', function (): void {
    it('creates default settings for post', function (): void {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $settings = ActivityPubPostSettings::getOrCreate($post);

        expect($settings->should_federate)->toBeFalse()
            ->and($settings->is_federated)->toBeFalse();
    });

    it('uses user default when creating', function (): void {
        $user = User::factory()->create();
        $userSettings = ActivityPubUserSettings::getOrCreate($user);
        $userSettings->update([
            'federation_enabled' => true,
            'default_federate_posts' => true,
        ]);

        $post = Post::factory()->create(['user_id' => $user->id]);
        $postSettings = ActivityPubPostSettings::getOrCreate($post);

        expect($postSettings->should_federate)->toBeTrue();
    });

    it('checks if post can federate', function (): void {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        // Initially cannot federate
        expect(ActivityPubPostSettings::canFederate($post))->toBeFalse();

        // Enable everything
        $userSettings = ActivityPubUserSettings::getOrCreate($user);
        $userSettings->update(['federation_enabled' => true]);

        ActivityPubPostSettings::getOrCreate($post, true);

        expect(ActivityPubPostSettings::canFederate($post))->toBeTrue();
    });
});

describe('MultiActorActivityPubService', function (): void {
    it('resolves webfinger for user', function (): void {
        $user = User::factory()->create(['username' => 'bob']);
        ActivityPubActor::findOrCreateForUser($user);

        $service = app(MultiActorActivityPubService::class);
        $actor = $service->resolveWebfinger('acct:bob@test.example.com');

        expect($actor)->not->toBeNull()
            ->and($actor->username)->toBe('bob')
            ->and($actor->isUser())->toBeTrue();
    });

    it('resolves webfinger for group with bang prefix', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'news',
            'display_name' => 'News',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);
        ActivityPubActor::findOrCreateForSub($sub);

        $service = app(MultiActorActivityPubService::class);
        $actor = $service->resolveWebfinger('acct:!news@test.example.com');

        expect($actor)->not->toBeNull()
            ->and($actor->username)->toBe('news')
            ->and($actor->isGroup())->toBeTrue();
    });

    it('resolves webfinger for instance', function (): void {
        ActivityPubActor::findOrCreateInstanceActor();

        $service = app(MultiActorActivityPubService::class);
        $actor = $service->resolveWebfinger('acct:testinstance@test.example.com');

        expect($actor)->not->toBeNull()
            ->and($actor->isInstance())->toBeTrue();
    });

    it('builds webfinger response', function (): void {
        $user = User::factory()->create(['username' => 'charlie']);
        $actor = ActivityPubActor::findOrCreateForUser($user);

        $service = app(MultiActorActivityPubService::class);
        $response = $service->buildWebfingerResponse($actor);

        expect($response['subject'])->toBe('acct:charlie@test.example.com')
            ->and($response['links'][0]['href'])->toBe($actor->actor_uri);
    });

    it('builds actor document', function (): void {
        $user = User::factory()->create(['username' => 'diana', 'bio' => 'Test bio']);
        $actor = ActivityPubActor::findOrCreateForUser($user);
        ActivityPubActorKey::ensureForActor($actor);
        $actor->load('keys');

        $document = $actor->toActivityPub();

        expect($document['type'])->toBe('Person')
            ->and($document['preferredUsername'])->toBe('diana')
            ->and($document['inbox'])->toContain('/inbox')
            ->and($document['publicKey'])->toBeArray();
    });
});

describe('WebFinger endpoint', function (): void {
    it('returns 404 for unknown resource', function (): void {
        $response = $this->get('/.well-known/webfinger?resource=acct:unknown@test.example.com');

        $response->assertStatus(404);
    });

    it('returns actor for valid user', function (): void {
        $user = User::factory()->create(['username' => 'webfingertest']);
        ActivityPubActor::findOrCreateForUser($user);

        $response = $this->get('/.well-known/webfinger?resource=acct:webfingertest@test.example.com');

        $response->assertStatus(200)
            ->assertJsonPath('subject', 'acct:webfingertest@test.example.com');
    });

    it('returns actor for valid group with bang', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'webfingergroup',
            'display_name' => 'Webfinger Group',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);
        ActivityPubActor::findOrCreateForSub($sub);

        $response = $this->get('/.well-known/webfinger?resource=acct:!webfingergroup@test.example.com');

        $response->assertStatus(200)
            ->assertJsonPath('subject', 'acct:!webfingergroup@test.example.com');
    });
});

describe('Actor endpoints', function (): void {
    it('returns user actor document', function (): void {
        $user = User::factory()->create(['username' => 'actortest']);
        $actor = ActivityPubActor::findOrCreateForUser($user);
        ActivityPubActorKey::ensureForActor($actor);

        $response = $this->get('/activitypub/users/actortest', [
            'Accept' => 'application/activity+json',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('type', 'Person')
            ->assertJsonPath('preferredUsername', 'actortest');
    });

    it('returns group actor document', function (): void {
        $user = User::factory()->create();
        $sub = Sub::create([
            'name' => 'grouptest',
            'display_name' => 'Group Test',
            'created_by' => $user->id,
            'color' => '#000000',
        ]);
        $actor = ActivityPubActor::findOrCreateForSub($sub);
        ActivityPubActorKey::ensureForActor($actor);

        $response = $this->get('/activitypub/groups/grouptest', [
            'Accept' => 'application/activity+json',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('type', 'Group')
            ->assertJsonPath('preferredUsername', 'grouptest');
    });

    it('returns 404 for nonexistent actor', function (): void {
        $response = $this->get('/activitypub/users/doesnotexist');

        $response->assertStatus(404);
    });
});

describe('API settings endpoints', function (): void {
    it('requires authentication for settings', function (): void {
        $response = $this->getJson('/api/v1/activitypub/settings');

        $response->assertStatus(401);
    });

    it('returns user federation settings', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/activitypub/settings');

        $response->assertStatus(200)
            ->assertJsonPath('federation_enabled', false)
            ->assertJsonPath('default_federate_posts', false);
    });

    it('updates user federation settings', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/v1/activitypub/settings', [
            'federation_enabled' => true,
            'default_federate_posts' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('federation_enabled', true)
            ->assertJsonPath('default_federate_posts', true);

        // Actor should be created
        $actor = ActivityPubActor::findByUsername($user->username, ActivityPubActor::TYPE_USER);
        expect($actor)->not->toBeNull();
    });

    it('returns post federation settings', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $post = Post::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/activitypub/posts/{$post->id}/settings");

        $response->assertStatus(200)
            ->assertJsonPath('should_federate', false)
            ->assertJsonPath('is_federated', false);
    });

    it('updates post federation settings', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $post = Post::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/activitypub/posts/{$post->id}/settings", [
            'should_federate' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('should_federate', true);
    });

    it('denies post settings to non-owner', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $post = Post::factory()->create(['user_id' => $owner->id]);
        Sanctum::actingAs($other);

        $response = $this->getJson("/api/v1/activitypub/posts/{$post->id}/settings");

        $response->assertStatus(401);
    });
});
