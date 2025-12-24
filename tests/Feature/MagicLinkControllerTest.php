<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\MagicLinkLogin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    // Set dummy Turnstile secret for testing
    Config::set('turnstile.secret_key', 'dummy-secret-key');

    // Mock Turnstile validation
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => true,
            'error-codes' => [],
        ], 200),
    ]);

    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'locale' => 'es',
    ]);
});

// sendMagicLink tests
test('sendMagicLink requires email', function (): void {
    $response = postJson('/api/v1/magic-link/email', [
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('sendMagicLink requires valid email', function (): void {
    $response = postJson('/api/v1/magic-link/email', [
        'email' => 'invalid-email',
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('sendMagicLink requires cf-turnstile-response', function (): void {
    $response = postJson('/api/v1/magic-link/email', [
        'email' => 'test@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['cf-turnstile-response']);
});

test('sendMagicLink fails with non-existent email', function (): void {
    $response = postJson('/api/v1/magic-link/email', [
        'email' => 'nonexistent@example.com',
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('sendMagicLink sends notification to user', function (): void {
    Notification::fake();

    $response = postJson('/api/v1/magic-link/email', [
        'email' => 'test@example.com',
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(200);
    Notification::assertSentTo($this->user, MagicLinkLogin::class);
});

test('sendMagicLink returns success message', function (): void {
    Notification::fake();

    $response = postJson('/api/v1/magic-link/email', [
        'email' => 'test@example.com',
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['message']);
});

// verifyMagicLink tests
test('verifyMagicLink requires token', function (): void {
    $response = postJson('/api/v1/magic-link/verify', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
});

test('verifyMagicLink fails with invalid token', function (): void {
    $response = postJson('/api/v1/magic-link/verify', [
        'token' => 'invalid-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
});

test('verifyMagicLink fails if token expired', function (): void {
    $token = 'expired-token-' . uniqid();
    Cache::put('magic_link_' . $token, $this->user->id, 1);
    sleep(2);

    $response = postJson('/api/v1/magic-link/verify', [
        'token' => $token,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
});

test('verifyMagicLink returns user and token with valid token', function (): void {
    $token = 'valid-token-' . uniqid();
    Cache::put('magic_link_' . $token, $this->user->id, 900);

    $response = postJson('/api/v1/magic-link/verify', [
        'token' => $token,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'user' => ['id', 'email'],
        'token',
    ]);
    expect($response->json('user.id'))->toBe($this->user->id);
});

test('verifyMagicLink removes token from cache after use', function (): void {
    $token = 'remove-token-' . uniqid();
    Cache::put('magic_link_' . $token, $this->user->id, 900);

    postJson('/api/v1/magic-link/verify', [
        'token' => $token,
    ]);

    expect(Cache::has('magic_link_' . $token))->toBeFalse();
});

test('verifyMagicLink creates authentication token', function (): void {
    $token = 'test-token';
    Cache::put('magic_link_' . $token, $this->user->id, 900);

    $response = postJson('/api/v1/magic-link/verify', [
        'token' => $token,
    ]);

    $response->assertStatus(200);
    expect($response->json('token'))->not()->toBeNull();
    expect(strlen($response->json('token')))->toBeGreaterThan(20);
});

test('verifyMagicLink fails if user does not exist', function (): void {
    $token = 'nouser-token-' . uniqid();
    Cache::put('magic_link_' . $token, 99999, 900);

    $response = postJson('/api/v1/magic-link/verify', [
        'token' => $token,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
});

test('verifyMagicLink cannot reuse used token', function (): void {
    $token = 'reuse-token-' . uniqid();
    Cache::put('magic_link_' . $token, $this->user->id, 900);

    postJson('/api/v1/magic-link/verify', [
        'token' => $token,
    ]);

    $response = postJson('/api/v1/magic-link/verify', [
        'token' => $token,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
});
