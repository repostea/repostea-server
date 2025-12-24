<?php

declare(strict_types=1);

use App\Models\MastodonApp;
use App\Models\User;
use App\Services\MbinOAuthService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = app(MbinOAuthService::class);

    config(['app.url' => 'https://example.com']);
    config(['app.frontend_url' => 'https://example.com']);
    config(['mail.from.address' => 'test@example.com']);
});

describe('Instance Normalization', function (): void {
    test('normalizes instance with https prefix', function (): void {
        Http::fake([
            'https://fedia.io/*' => Http::response([
                'identifier' => 'test_id',
                'secret' => 'test_secret',
            ], 200),
        ]);

        $app = $this->service->getOrCreateApp('https://fedia.io');

        expect($app)->not->toBeNull();
        expect($app->instance)->toBe('fedia.io');
    });

    test('normalizes instance to lowercase', function (): void {
        Http::fake([
            'https://fedia.io/*' => Http::response([
                'identifier' => 'test_id',
                'secret' => 'test_secret',
            ], 200),
        ]);

        $app = $this->service->getOrCreateApp('FEDIA.IO');

        expect($app)->not->toBeNull();
        expect($app->instance)->toBe('fedia.io');
    });
});

describe('App Registration', function (): void {
    test('getOrCreateApp returns existing app', function (): void {
        $existingApp = MastodonApp::create([
            'instance' => 'fedia.io',
            'client_id' => 'existing_id',
            'client_secret' => 'existing_secret',
        ]);

        $app = $this->service->getOrCreateApp('fedia.io');

        expect($app->id)->toBe($existingApp->id);
    });

    test('getOrCreateApp registers new app with Mbin API', function (): void {
        Http::fake([
            'https://newmbin.io/api/client' => Http::response([
                'identifier' => 'mbin_client_id',
                'secret' => 'mbin_client_secret',
            ], 200),
        ]);

        $app = $this->service->getOrCreateApp('newmbin.io');

        expect($app)->not->toBeNull();
        expect($app->client_id)->toBe('mbin_client_id');
        expect($app->client_secret)->toBe('mbin_client_secret');
    });

    test('getOrCreateApp returns null on failure and sets error code', function (): void {
        Http::fake([
            '*' => Http::response('Error', 500),
        ]);

        $app = $this->service->getOrCreateApp('failing.io');

        expect($app)->toBeNull();
        expect($this->service->getLastErrorCode())->toBe(500);
    });
});

describe('Authorization URL', function (): void {
    test('getAuthorizationUrl returns Mbin authorize URL', function (): void {
        MastodonApp::create([
            'instance' => 'fedia.io',
            'client_id' => 'mbin_client_id',
            'client_secret' => 'mbin_secret',
        ]);

        $url = $this->service->getAuthorizationUrl('fedia.io', 'test_state');

        expect($url)->toContain('https://fedia.io/authorize');
        expect($url)->toContain('client_id=mbin_client_id');
        expect($url)->toContain('state=test_state');
    });

    test('getAuthorizationUrl returns null when app not exists', function (): void {
        Http::fake([
            '*' => Http::response('Error', 500),
        ]);

        $url = $this->service->getAuthorizationUrl('unknown.io', 'state');

        expect($url)->toBeNull();
    });
});

describe('Access Token', function (): void {
    test('getAccessToken exchanges code for token via /token endpoint', function (): void {
        MastodonApp::create([
            'instance' => 'fedia.io',
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
        ]);

        Http::fake([
            'https://fedia.io/token' => Http::response([
                'access_token' => 'mbin_access_token',
            ], 200),
        ]);

        $token = $this->service->getAccessToken('fedia.io', 'auth_code');

        expect($token)->toBe('mbin_access_token');
    });

    test('getAccessToken returns null when app not exists', function (): void {
        $token = $this->service->getAccessToken('unknown.io', 'code');

        expect($token)->toBeNull();
    });

    test('getAccessToken returns null on failure', function (): void {
        MastodonApp::create([
            'instance' => 'fedia.io',
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
        ]);

        Http::fake([
            '*' => Http::response('Error', 401),
        ]);

        $token = $this->service->getAccessToken('fedia.io', 'invalid_code');

        expect($token)->toBeNull();
    });
});

describe('Account Info', function (): void {
    test('getAccountInfo tries /api/users/me first', function (): void {
        Http::fake([
            'https://fedia.io/api/users/me' => Http::response([
                'userId' => 123,
                'username' => 'mbinuser',
                'about' => 'Test bio',
            ], 200),
        ]);

        $info = $this->service->getAccountInfo('fedia.io', 'access_token');

        expect($info)->not->toBeNull();
        expect($info['username'])->toBe('mbinuser');
        expect($info['userId'])->toBe(123);
    });

    test('getAccountInfo falls back to /api/user', function (): void {
        Http::fake([
            'https://fedia.io/api/users/me' => Http::response('Not Found', 404),
            'https://fedia.io/api/user' => Http::response([
                'userId' => 456,
                'username' => 'fallbackuser',
            ], 200),
        ]);

        $info = $this->service->getAccountInfo('fedia.io', 'access_token');

        expect($info)->not->toBeNull();
        expect($info['username'])->toBe('fallbackuser');
    });

    test('getAccountInfo returns null when both endpoints fail', function (): void {
        Http::fake([
            '*' => Http::response('Unauthorized', 401),
        ]);

        $info = $this->service->getAccountInfo('fedia.io', 'invalid_token');

        expect($info)->toBeNull();
    });
});

describe('User Creation', function (): void {
    test('findOrCreateUser creates new user from Mbin data', function (): void {
        $accountInfo = [
            'userId' => 12345,
            'username' => 'mbinuser',
            'about' => 'Mbin bio',
            'avatar' => 'https://fedia.io/avatar.png',
            'createdAt' => '2024-01-01T00:00:00Z',
        ];

        $user = $this->service->findOrCreateUser('fedia.io', $accountInfo);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->username)->toBe('mbinuser@fedia.io');
        expect($user->federated_id)->toBe('12345@fedia.io');
        expect($user->federated_instance)->toBe('fedia.io');
        expect($user->bio)->toBe('Mbin bio');
    });

    test('findOrCreateUser uses id field if userId missing', function (): void {
        $accountInfo = [
            'id' => 67890,
            'username' => 'iduser',
        ];

        $user = $this->service->findOrCreateUser('fedia.io', $accountInfo);

        expect($user->federated_id)->toBe('67890@fedia.io');
    });

    test('findOrCreateUser throws when no user ID', function (): void {
        $accountInfo = [
            'username' => 'noiduser',
        ];

        expect(fn () => $this->service->findOrCreateUser('fedia.io', $accountInfo))
            ->toThrow(Exception::class);
    });

    test('findOrCreateUser returns existing user', function (): void {
        $existingUser = User::create([
            'username' => 'existing@fedia.io',
            'email' => null,
            'password' => bcrypt('test'),
            'federated_id' => '12345@fedia.io',
            'federated_instance' => 'fedia.io',
            'status' => 'approved',
        ]);

        $accountInfo = [
            'userId' => 12345,
            'username' => 'existing',
        ];

        $user = $this->service->findOrCreateUser('fedia.io', $accountInfo);

        expect($user->id)->toBe($existingUser->id);
    });

    test('findOrCreateUser handles nested avatar object', function (): void {
        $accountInfo = [
            'userId' => 11111,
            'username' => 'avataruser',
            'avatar' => [
                'filePath' => 'https://fedia.io/nested-avatar.png',
            ],
        ];

        $user = $this->service->findOrCreateUser('fedia.io', $accountInfo);

        expect($user->avatar_url)->toBe('https://fedia.io/nested-avatar.png');
    });

    test('findOrCreateUser handles avatar with storageUrl', function (): void {
        $accountInfo = [
            'userId' => 22222,
            'username' => 'storageuser',
            'avatar' => [
                'storageUrl' => 'https://storage.fedia.io/avatar.png',
            ],
        ];

        $user = $this->service->findOrCreateUser('fedia.io', $accountInfo);

        expect($user->avatar_url)->toBe('https://storage.fedia.io/avatar.png');
    });
});

describe('Instance Validation', function (): void {
    test('isInstanceBlocked returns true for blocked instance', function (): void {
        config(['fediverse_login.blocked_instances' => ['blocked.io']]);

        expect($this->service->isInstanceBlocked('blocked.io'))->toBeTrue();
    });

    test('validateMbinInstance returns true for valid Mbin instance', function (): void {
        Http::fake([
            'https://fedia.io/api/info' => Http::response(['name' => 'Fedia'], 200),
        ]);

        expect($this->service->validateMbinInstance('fedia.io'))->toBeTrue();
    });

    test('validateMbinInstance checks nodeinfo as fallback', function (): void {
        Http::fake([
            'https://kbin.social/api/info' => Http::response('Not Found', 404),
            'https://kbin.social/.well-known/nodeinfo' => Http::response([
                'links' => [
                    ['href' => 'https://kbin.social/nodeinfo/2.0'],
                ],
            ], 200),
            'https://kbin.social/nodeinfo/2.0' => Http::response([
                'software' => ['name' => 'kbin'],
            ], 200),
        ]);

        expect($this->service->validateMbinInstance('kbin.social'))->toBeTrue();
    });

    test('validateMbinInstance returns false for non-Mbin instance', function (): void {
        Http::fake([
            'https://mastodon.social/api/info' => Http::response('Not Found', 404),
            'https://mastodon.social/.well-known/nodeinfo' => Http::response([
                'links' => [
                    ['href' => 'https://mastodon.social/nodeinfo/2.0'],
                ],
            ], 200),
            'https://mastodon.social/nodeinfo/2.0' => Http::response([
                'software' => ['name' => 'mastodon'],
            ], 200),
        ]);

        expect($this->service->validateMbinInstance('mastodon.social'))->toBeFalse();
    });

    test('validateMbinInstance rejects localhost', function (): void {
        expect($this->service->validateMbinInstance('localhost'))->toBeFalse();
    });
});
