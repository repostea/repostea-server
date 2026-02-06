<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\BlueskyOAuthService;

beforeEach(function (): void {
    $this->service = app(BlueskyOAuthService::class);
});

describe('extractInstance', function (): void {
    test('extracts instance from standard bsky handle', function (): void {
        $instance = $this->service->extractInstance('alice.bsky.social');

        expect($instance)->toBe('bsky.social');
    });

    test('extracts instance from custom domain handle', function (): void {
        $instance = $this->service->extractInstance('bob.custom-domain.com');

        expect($instance)->toBe('custom-domain.com');
    });

    test('returns full handle when only two parts', function (): void {
        $instance = $this->service->extractInstance('example.com');

        expect($instance)->toBe('example.com');
    });

    test('handles deeply nested domains', function (): void {
        $instance = $this->service->extractInstance('user.sub.domain.example.com');

        expect($instance)->toBe('sub.domain.example.com');
    });
});

describe('findOrCreateUser', function (): void {
    test('creates a new user from Bluesky data', function (): void {
        $userData = [
            'did' => 'did:plc:abc123',
            'handle' => 'alice.bsky.social',
            'displayName' => 'Alice',
            'avatar' => 'https://cdn.bsky.app/avatar.jpg',
            'createdAt' => '2024-01-15T12:00:00.000Z',
        ];

        $user = $this->service->findOrCreateUser($userData);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->username)->toBe('alice.bsky.social@bluesky');
        expect($user->display_name)->toBe('Alice');
        expect($user->avatar_url)->toBe('https://cdn.bsky.app/avatar.jpg');
        expect($user->federated_id)->toBe('did:plc:abc123@bsky');
        expect($user->federated_instance)->toBe('bsky.social');
        expect($user->federated_username)->toBe('alice.bsky.social');
        expect($user->email_verified_at)->not->toBeNull();
        expect($user->status)->toBe('approved');
    });

    test('finds existing user by federated_id', function (): void {
        $existing = User::factory()->create([
            'federated_id' => 'did:plc:existing@bsky',
            'federated_instance' => 'bsky.social',
            'username' => 'existing.bsky.social@bluesky',
        ]);

        $userData = [
            'did' => 'did:plc:existing',
            'handle' => 'existing.bsky.social',
            'displayName' => 'Updated Name',
            'avatar' => 'https://cdn.bsky.app/new-avatar.jpg',
            'createdAt' => null,
        ];

        $user = $this->service->findOrCreateUser($userData);

        expect($user->id)->toBe($existing->id);
    });

    test('restores soft-deleted user', function (): void {
        $deleted = User::factory()->create([
            'federated_id' => 'did:plc:deleted@bsky',
            'federated_instance' => 'bsky.social',
            'username' => 'deleted.bsky.social@bluesky',
        ]);
        $deleted->delete();

        $userData = [
            'did' => 'did:plc:deleted',
            'handle' => 'deleted.bsky.social',
            'displayName' => 'Restored User',
            'avatar' => null,
            'createdAt' => null,
        ];

        $user = $this->service->findOrCreateUser($userData);

        expect($user->id)->toBe($deleted->id);
        expect($user->trashed())->toBeFalse();
    });

    test('handles username collision with numeric suffix', function (): void {
        // Pre-create a user with the same username
        User::factory()->create([
            'username' => 'alice.bsky.social@bluesky',
        ]);

        $userData = [
            'did' => 'did:plc:alice2',
            'handle' => 'alice.bsky.social',
            'displayName' => 'Alice 2',
            'avatar' => null,
            'createdAt' => null,
        ];

        $user = $this->service->findOrCreateUser($userData);

        expect($user->username)->toBe('alice.bsky.social@bluesky1');
    });

    test('updates avatar when it changes', function (): void {
        $existing = User::factory()->create([
            'federated_id' => 'did:plc:avatartest@bsky',
            'avatar_url' => 'https://cdn.bsky.app/old-avatar.jpg',
        ]);

        $userData = [
            'did' => 'did:plc:avatartest',
            'handle' => 'test.bsky.social',
            'displayName' => $existing->display_name,
            'avatar' => 'https://cdn.bsky.app/new-avatar.jpg',
            'createdAt' => null,
        ];

        $user = $this->service->findOrCreateUser($userData);

        expect($user->avatar_url)->toBe('https://cdn.bsky.app/new-avatar.jpg');
    });

    test('uses handle as display name when displayName is null', function (): void {
        $userData = [
            'did' => 'did:plc:noname',
            'handle' => 'noname.bsky.social',
            'displayName' => null,
            'avatar' => null,
            'createdAt' => null,
        ];

        $user = $this->service->findOrCreateUser($userData);

        expect($user->display_name)->toBe('noname.bsky.social');
    });

    test('parses ISO 8601 createdAt date', function (): void {
        $userData = [
            'did' => 'did:plc:datetest',
            'handle' => 'datetest.bsky.social',
            'displayName' => 'Date Test',
            'avatar' => null,
            'createdAt' => '2023-06-15T10:30:00.000Z',
        ];

        $user = $this->service->findOrCreateUser($userData);

        expect($user->federated_account_created_at)->not->toBeNull();
        expect($user->federated_account_created_at->year)->toBe(2023);
        expect($user->federated_account_created_at->month)->toBe(6);
    });
});
