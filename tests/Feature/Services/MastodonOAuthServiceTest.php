<?php

declare(strict_types=1);

use App\Models\MastodonApp;
use App\Models\User;
use App\Services\MastodonOAuthService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = app(MastodonOAuthService::class);

    config(['app.url' => 'https://example.com']);
    config(['app.frontend_url' => 'https://example.com']);
});

describe('Instance Normalization', function (): void {
    test('normalizes instance with https prefix', function (): void {
        Http::fake([
            'https://mastodon.social/*' => Http::response([
                'client_id' => 'test_id',
                'client_secret' => 'test_secret',
            ], 200),
        ]);

        $app = $this->service->getOrCreateApp('https://mastodon.social');

        expect($app)->not->toBeNull();
        expect($app->instance)->toBe('mastodon.social');
    });

    test('normalizes instance with trailing slash', function (): void {
        Http::fake([
            'https://mastodon.social/*' => Http::response([
                'client_id' => 'test_id',
                'client_secret' => 'test_secret',
            ], 200),
        ]);

        $app = $this->service->getOrCreateApp('mastodon.social/');

        expect($app)->not->toBeNull();
        expect($app->instance)->toBe('mastodon.social');
    });

    test('normalizes instance to lowercase', function (): void {
        Http::fake([
            'https://mastodon.social/*' => Http::response([
                'client_id' => 'test_id',
                'client_secret' => 'test_secret',
            ], 200),
        ]);

        $app = $this->service->getOrCreateApp('MASTODON.SOCIAL');

        expect($app)->not->toBeNull();
        expect($app->instance)->toBe('mastodon.social');
    });
});

describe('App Registration', function (): void {
    test('getOrCreateApp returns existing app', function (): void {
        $existingApp = MastodonApp::create([
            'instance' => 'mastodon.social',
            'client_id' => 'existing_id',
            'client_secret' => 'existing_secret',
        ]);

        $app = $this->service->getOrCreateApp('mastodon.social');

        expect($app->id)->toBe($existingApp->id);
    });

    test('getOrCreateApp registers new app when not exists', function (): void {
        Http::fake([
            'https://newinstance.social/*' => Http::response([
                'client_id' => 'new_id',
                'client_secret' => 'new_secret',
            ], 200),
        ]);

        $app = $this->service->getOrCreateApp('newinstance.social');

        expect($app)->not->toBeNull();
        expect($app->client_id)->toBe('new_id');
        expect($app->client_secret)->toBe('new_secret');
        expect(MastodonApp::where('instance', 'newinstance.social')->exists())->toBeTrue();
    });

    test('getOrCreateApp returns null on registration failure', function (): void {
        Http::fake([
            'https://failing.social/*' => Http::response('Error', 500),
        ]);

        $app = $this->service->getOrCreateApp('failing.social');

        expect($app)->toBeNull();
    });

    test('getOrCreateApp rejects invalid instance', function (): void {
        expect(fn () => $this->service->getOrCreateApp('localhost'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('Authorization URL', function (): void {
    test('getAuthorizationUrl returns valid URL', function (): void {
        MastodonApp::create([
            'instance' => 'mastodon.social',
            'client_id' => 'test_client_id',
            'client_secret' => 'test_secret',
        ]);

        $url = $this->service->getAuthorizationUrl('mastodon.social', 'test_state');

        expect($url)->toContain('https://mastodon.social/oauth/authorize');
        expect($url)->toContain('client_id=test_client_id');
        expect($url)->toContain('state=test_state');
        expect($url)->toContain('response_type=code');
    });

    test('getAuthorizationUrl returns null when app not exists and registration fails', function (): void {
        Http::fake([
            '*' => Http::response('Error', 500),
        ]);

        $url = $this->service->getAuthorizationUrl('unknown.social', 'state');

        expect($url)->toBeNull();
    });
});

describe('Access Token', function (): void {
    test('getAccessToken exchanges code for token', function (): void {
        MastodonApp::create([
            'instance' => 'mastodon.social',
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
        ]);

        Http::fake([
            'https://mastodon.social/oauth/token' => Http::response([
                'access_token' => 'test_access_token',
            ], 200),
        ]);

        $token = $this->service->getAccessToken('mastodon.social', 'auth_code');

        expect($token)->toBe('test_access_token');
    });

    test('getAccessToken returns null when app not exists', function (): void {
        $token = $this->service->getAccessToken('unknown.social', 'code');

        expect($token)->toBeNull();
    });

    test('getAccessToken returns null on failure', function (): void {
        MastodonApp::create([
            'instance' => 'mastodon.social',
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
        ]);

        Http::fake([
            '*' => Http::response('Error', 401),
        ]);

        $token = $this->service->getAccessToken('mastodon.social', 'invalid_code');

        expect($token)->toBeNull();
    });
});

describe('Account Info', function (): void {
    test('getAccountInfo returns user data', function (): void {
        Http::fake([
            'https://mastodon.social/api/v1/accounts/verify_credentials' => Http::response([
                'id' => '12345',
                'username' => 'testuser',
                'display_name' => 'Test User',
                'avatar' => 'https://mastodon.social/avatar.png',
            ], 200),
        ]);

        $info = $this->service->getAccountInfo('mastodon.social', 'access_token');

        expect($info)->not->toBeNull();
        expect($info['username'])->toBe('testuser');
        expect($info['id'])->toBe('12345');
    });

    test('getAccountInfo returns null on failure', function (): void {
        Http::fake([
            '*' => Http::response('Unauthorized', 401),
        ]);

        $info = $this->service->getAccountInfo('mastodon.social', 'invalid_token');

        expect($info)->toBeNull();
    });
});

describe('User Creation', function (): void {
    test('findOrCreateUser creates new user', function (): void {
        $accountInfo = [
            'id' => '12345',
            'username' => 'newuser',
            'display_name' => 'New User',
            'note' => 'Test bio',
            'avatar' => 'https://mastodon.social/avatar.png',
            'created_at' => '2024-01-01T00:00:00Z',
        ];

        $user = $this->service->findOrCreateUser('mastodon.social', $accountInfo);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->username)->toBe('newuser@mastodon.social');
        expect($user->display_name)->toBe('New User');
        expect($user->federated_id)->toBe('12345@mastodon.social');
        expect($user->federated_instance)->toBe('mastodon.social');
        expect($user->status)->toBe('approved');
    });

    test('findOrCreateUser returns existing user', function (): void {
        $existingUser = User::create([
            'username' => 'existing@mastodon.social',
            'email' => null,
            'password' => bcrypt('test'),
            'federated_id' => '12345@mastodon.social',
            'federated_instance' => 'mastodon.social',
            'status' => 'approved',
        ]);

        $accountInfo = [
            'id' => '12345',
            'username' => 'existing',
        ];

        $user = $this->service->findOrCreateUser('mastodon.social', $accountInfo);

        expect($user->id)->toBe($existingUser->id);
    });

    test('findOrCreateUser restores soft-deleted user', function (): void {
        $deletedUser = User::create([
            'username' => 'deleted@mastodon.social',
            'email' => null,
            'password' => bcrypt('test'),
            'federated_id' => '99999@mastodon.social',
            'federated_instance' => 'mastodon.social',
            'status' => 'approved',
        ]);
        $deletedUser->delete();

        $accountInfo = [
            'id' => '99999',
            'username' => 'deleted',
        ];

        $user = $this->service->findOrCreateUser('mastodon.social', $accountInfo);

        expect($user->id)->toBe($deletedUser->id);
        expect($user->trashed())->toBeFalse();
    });

    test('findOrCreateUser handles username collision', function (): void {
        User::create([
            'username' => 'collision@mastodon.social',
            'email' => 'collision@test.com',
            'password' => bcrypt('test'),
            'status' => 'approved',
        ]);

        $accountInfo = [
            'id' => '11111',
            'username' => 'collision',
            'display_name' => 'Collision User',
        ];

        $user = $this->service->findOrCreateUser('mastodon.social', $accountInfo);

        expect($user->username)->toBe('collision@mastodon.social1');
    });

    test('findOrCreateUser updates avatar on existing user', function (): void {
        $existingUser = User::create([
            'username' => 'avatartest@mastodon.social',
            'email' => null,
            'password' => bcrypt('test'),
            'federated_id' => '77777@mastodon.social',
            'federated_instance' => 'mastodon.social',
            'avatar_url' => 'https://old-avatar.png',
            'status' => 'approved',
        ]);

        $accountInfo = [
            'id' => '77777',
            'username' => 'avatartest',
            'avatar' => 'https://new-avatar.png',
        ];

        $user = $this->service->findOrCreateUser('mastodon.social', $accountInfo);

        expect($user->fresh()->avatar_url)->toBe('https://new-avatar.png');
    });
});

describe('Instance Validation', function (): void {
    test('isInstanceBlocked returns true for blocked instance', function (): void {
        config(['fediverse_login.blocked_instances' => ['blocked.social']]);

        expect($this->service->isInstanceBlocked('blocked.social'))->toBeTrue();
    });

    test('isInstanceBlocked returns false for allowed instance', function (): void {
        config(['fediverse_login.blocked_instances' => ['other.social']]);

        expect($this->service->isInstanceBlocked('allowed.social'))->toBeFalse();
    });

    test('validateMastodonInstance returns true for valid instance', function (): void {
        Http::fake([
            'https://mastodon.social/api/v1/instance' => Http::response(['uri' => 'mastodon.social'], 200),
        ]);

        expect($this->service->validateMastodonInstance('mastodon.social'))->toBeTrue();
    });

    test('validateMastodonInstance returns false for invalid instance', function (): void {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        expect($this->service->validateMastodonInstance('notmastodon.social'))->toBeFalse();
    });

    test('validateMastodonInstance rejects localhost', function (): void {
        expect($this->service->validateMastodonInstance('localhost'))->toBeFalse();
    });
});
