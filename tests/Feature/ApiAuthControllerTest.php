<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'username' => 'testuser',
        'password' => Hash::make('password123'),
    ]);
});

// login tests
test('login requires email', function (): void {
    $response = postJson('/api/v1/login', [
        'password' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('login requires password', function (): void {
    $response = postJson('/api/v1/login', [
        'email' => 'test@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

test('login with correct credentials returns token', function (): void {
    $response = postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'user',
        'token',
        'email_verification_required',
    ]);
    expect($response->json('token'))->not()->toBeNull();
});

test('login accepts username instead of email', function (): void {
    $response = postJson('/api/v1/login', [
        'email' => 'testuser',
        'password' => 'password123',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'user',
        'token',
    ]);
});

test('login with incorrect password returns error', function (): void {
    $response = postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('login with non-existent email returns error', function (): void {
    $response = postJson('/api/v1/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('login is case insensitive for email', function (): void {
    $response = postJson('/api/v1/login', [
        'email' => 'TEST@EXAMPLE.COM',
        'password' => 'password123',
    ]);

    $response->assertStatus(200);
});

test('login deletes only tokens inactive for more than 30 days', function (): void {
    // Create an old token (inactive for 31 days)
    $oldToken = $this->user->createToken('old_token');
    $oldTokenModel = $oldToken->accessToken;
    $oldTokenModel->last_used_at = now()->subDays(31);
    $oldTokenModel->created_at = now()->subDays(60);
    $oldTokenModel->save();

    // Create a recent token (used 5 days ago)
    $recentToken = $this->user->createToken('recent_token');
    $recentTokenModel = $recentToken->accessToken;
    $recentTokenModel->last_used_at = now()->subDays(5);
    $recentTokenModel->save();

    expect($this->user->tokens()->count())->toBe(2);

    postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    // Should have 2 tokens: the recent one (kept) + the new one from login
    // The old inactive token should be deleted
    expect($this->user->fresh()->tokens()->count())->toBe(2);
});

test('login keeps recent user tokens for multi-device', function (): void {
    $token = $this->user->createToken('device_token');
    $tokenModel = $token->accessToken;
    $tokenModel->last_used_at = now()->subHours(1);
    $tokenModel->save();

    expect($this->user->tokens()->count())->toBe(1);

    postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    // Should have 2 tokens: the existing recent one + the new one
    expect($this->user->fresh()->tokens()->count())->toBe(2);
});

test('login prevents deleted users from logging in', function (): void {
    $this->user->update(['is_deleted' => true]);

    $response = postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('login indicates if email verification is required', function (): void {
    SystemSetting::set('email_verification', 'required');
    $this->user->update(['email_verified_at' => null]);

    $response = postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200);
    expect($response->json('email_verification_required'))->toBeTrue();
});

test('login does not require verification if email already verified', function (): void {
    SystemSetting::set('email_verification', 'required');
    $this->user->update(['email_verified_at' => now()]);

    $response = postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200);
    expect($response->json('email_verification_required'))->toBeFalse();
});

test('login applies rate limiting after 5 failed attempts', function (): void {
    for ($i = 0; $i < 5; $i++) {
        postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);
    }

    $response = postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

// guestLogin tests
test('guestLogin creates guest user', function (): void {
    $response = postJson('/api/v1/guest-login');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'user' => ['id', 'username', 'display_name'],
        'token',
    ]);

    $user = User::where('id', $response->json('user.id'))->first();
    expect($user->is_guest)->toBeTrue();
});

test('guestLogin returns authentication token', function (): void {
    $response = postJson('/api/v1/guest-login');

    $response->assertStatus(200);
    expect($response->json('token'))->not()->toBeNull();
});

test('guestLogin generates unique temporary email', function (): void {
    $response1 = postJson('/api/v1/guest-login');
    $response2 = postJson('/api/v1/guest-login');

    $email1 = User::find($response1->json('user.id'))->email;
    $email2 = User::find($response2->json('user.id'))->email;

    expect($email1)->not()->toBe($email2);
    expect($email1)->toContain('guest_');
    expect($email1)->toContain('@temp.local');
});

test('guestLogin genera display_name legible', function (): void {
    $response = postJson('/api/v1/guest-login');

    $displayName = $response->json('user.display_name');
    expect($displayName)->not()->toBeNull();
    expect(strlen($displayName))->toBeGreaterThan(3);
});

test('guestLogin generates unique username', function (): void {
    $response1 = postJson('/api/v1/guest-login');
    $response2 = postJson('/api/v1/guest-login');

    $username1 = $response1->json('user.username');
    $username2 = $response2->json('user.username');

    expect($username1)->not()->toBe($username2);
});

test('guestLogin marks email as automatically verified', function (): void {
    $response = postJson('/api/v1/guest-login');

    $user = User::find($response->json('user.id'));
    expect($user->email_verified_at)->not()->toBeNull();
});

test('guestLogin respects guest_access configuration', function (): void {
    SystemSetting::set('guest_access', 'disabled');

    $response = postJson('/api/v1/guest-login');

    $response->assertStatus(403);
});

// logout tests
test('logout requires authentication', function (): void {
    $response = postJson('/api/v1/logout');

    $response->assertStatus(401);
});

test('logout deletes current user token', function (): void {
    $token1 = $this->user->createToken('token1')->plainTextToken;
    $this->user->createToken('token2');

    expect($this->user->tokens()->count())->toBe(2);

    $response = postJson('/api/v1/logout', [], [
        'Authorization' => 'Bearer ' . $token1,
    ]);

    $response->assertStatus(200);
    expect($this->user->fresh()->tokens()->count())->toBe(1);
});

test('logout returns success message', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/logout');

    $response->assertStatus(200);
    $response->assertJsonStructure(['message']);
});

// user tests
test('user requires authentication', function (): void {
    $response = getJson('/api/v1/user');

    $response->assertStatus(401);
});

test('user returns authenticated user information', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/user');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['id', 'username', 'email'],
        'posts_count',
        'comments_count',
        'member_since',
        'achievements',
    ]);
});

test('user includes post count', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/user');

    $response->assertStatus(200);
    expect($response->json('posts_count'))->toBeInt();
});

test('user includes comment count', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/user');

    $response->assertStatus(200);
    expect($response->json('comments_count'))->toBeInt();
});

test('user includes registration date', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/user');

    $response->assertStatus(200);
    expect($response->json('member_since'))->not()->toBeNull();
});

test('user includes achievements information', function (): void {
    Sanctum::actingAs($this->user);

    Achievement::factory()->count(3)->create();

    $response = getJson('/api/v1/user');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'achievements' => [
            'items',
            'unlocked_count',
            'total_count',
        ],
    ]);
});

test('user includes achievement progress', function (): void {
    Sanctum::actingAs($this->user);

    $achievement = Achievement::factory()->create();

    $response = getJson('/api/v1/user');

    $response->assertStatus(200);
    expect($response->json('achievements.items'))->toBeArray();
});

test('user loads user current level', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/user');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['current_level'],
    ]);
});
