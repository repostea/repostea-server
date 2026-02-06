<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;

describe('Bluesky Auth Status', function (): void {
    test('returns enabled true when configured', function (): void {
        config([
            'bluesky_login.enabled' => true,
            'bluesky_login.private_key' => 'test-private-key',
        ]);

        $response = $this->getJson('/api/v1/auth/bluesky/status');

        $response->assertOk();
        $response->assertJson(['enabled' => true]);
    });

    test('returns enabled false when disabled', function (): void {
        config(['bluesky_login.enabled' => false]);

        $response = $this->getJson('/api/v1/auth/bluesky/status');

        $response->assertOk();
        $response->assertJson(['enabled' => false]);
    });

    test('returns enabled false when private key is missing', function (): void {
        config([
            'bluesky_login.enabled' => true,
            'bluesky_login.private_key' => '',
        ]);

        $response = $this->getJson('/api/v1/auth/bluesky/status');

        $response->assertOk();
        $response->assertJson(['enabled' => false]);
    });
});

describe('Bluesky Auth Exchange', function (): void {
    test('exchanges valid code for token', function (): void {
        $user = User::factory()->create(['status' => 'approved']);
        $code = str_repeat('a', 64);

        Cache::put("bluesky_exchange:{$code}", ['user_id' => $user->id], 60);

        $response = $this->postJson('/api/v1/auth/bluesky/exchange', [
            'exchange_code' => $code,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'user' => ['id', 'username', 'display_name', 'is_federated'],
        ]);
        expect($response->json('user.id'))->toBe($user->id);
        expect($response->json('user.is_federated'))->toBeTrue();
    });

    test('rejects invalid exchange code', function (): void {
        $code = str_repeat('b', 64);

        $response = $this->postJson('/api/v1/auth/bluesky/exchange', [
            'exchange_code' => $code,
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Invalid or expired exchange code']);
    });

    test('exchange code can only be used once', function (): void {
        $user = User::factory()->create(['status' => 'approved']);
        $code = str_repeat('c', 64);

        Cache::put("bluesky_exchange:{$code}", ['user_id' => $user->id], 60);

        // First use should succeed
        $this->postJson('/api/v1/auth/bluesky/exchange', [
            'exchange_code' => $code,
        ])->assertOk();

        // Second use should fail (code consumed)
        $this->postJson('/api/v1/auth/bluesky/exchange', [
            'exchange_code' => $code,
        ])->assertStatus(400);
    });

    test('rejects exchange for banned user', function (): void {
        $user = User::factory()->create(['status' => 'approved']);
        // Ban the user
        $user->bans()->create([
            'reason' => 'Test ban',
            'banned_by' => $user->id,
            'expires_at' => null,
        ]);

        $code = str_repeat('d', 64);
        Cache::put("bluesky_exchange:{$code}", ['user_id' => $user->id], 60);

        $response = $this->postJson('/api/v1/auth/bluesky/exchange', [
            'exchange_code' => $code,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Your account has been banned']);
    });

    test('rejects exchange for pending user when auto_approve is off', function (): void {
        $user = User::factory()->create(['status' => 'pending']);
        $code = str_repeat('e', 64);

        config(['bluesky_login.auto_approve' => false]);
        Cache::put("bluesky_exchange:{$code}", ['user_id' => $user->id], 60);

        $response = $this->postJson('/api/v1/auth/bluesky/exchange', [
            'exchange_code' => $code,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Your account is pending approval',
            'status' => 'pending',
        ]);
    });

    test('validates exchange_code format', function (): void {
        $response = $this->postJson('/api/v1/auth/bluesky/exchange', [
            'exchange_code' => 'short',
        ]);

        $response->assertStatus(422);
    });

    test('requires exchange_code field', function (): void {
        $response = $this->postJson('/api/v1/auth/bluesky/exchange', []);

        $response->assertStatus(422);
    });
});
