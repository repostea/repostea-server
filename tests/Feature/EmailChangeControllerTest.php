<?php

declare(strict_types=1);

use App\Models\ModerationLog;
use App\Models\User;
use App\Notifications\EmailChangeConfirmation;
use App\Notifications\EmailChangeRequested;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'original@example.com',
        'password' => Hash::make('current-password'),
        'email_verified_at' => now(),
    ]);
});

// Request tests
test('request email change requires authentication', function (): void {
    $response = postJson('/api/v1/user/email/change', [
        'email' => 'new@example.com',
        'current_password' => 'current-password',
    ]);

    $response->assertStatus(401);
});

test('request email change requires current password', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/user/email/change', [
        'email' => 'new@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['current_password']);
});

test('request email change validates current password', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/user/email/change', [
        'email' => 'new@example.com',
        'current_password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['current_password']);
});

test('request email change requires valid email', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/user/email/change', [
        'email' => 'not-an-email',
        'current_password' => 'current-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('request email change requires unique email', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/user/email/change', [
        'email' => 'taken@example.com',
        'current_password' => 'current-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('request email change rejects same email', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/user/email/change', [
        'email' => 'original@example.com',
        'current_password' => 'current-password',
    ]);

    $response->assertStatus(422);
});

test('request email change stores pending email and token', function (): void {
    Notification::fake();
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/user/email/change', [
        'email' => 'new@example.com',
        'current_password' => 'current-password',
    ]);

    $response->assertStatus(200);

    $this->user->refresh();
    expect($this->user->pending_email)->toBe('new@example.com');
    expect($this->user->email_change_token)->not()->toBeNull();
    expect(strlen($this->user->email_change_token))->toBe(64);
    expect($this->user->email_change_requested_at)->not()->toBeNull();
});

test('request email change sends notifications', function (): void {
    Notification::fake();
    Sanctum::actingAs($this->user);

    postJson('/api/v1/user/email/change', [
        'email' => 'new@example.com',
        'current_password' => 'current-password',
    ]);

    Notification::assertSentTo($this->user, EmailChangeRequested::class);
    Notification::assertSentTo($this->user, EmailChangeConfirmation::class);
});

// Confirm tests
test('confirm email change requires token', function (): void {
    $response = postJson('/api/v1/email/change/confirm', [
        'token' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
});

test('confirm email change rejects invalid token', function (): void {
    $response = postJson('/api/v1/email/change/confirm', [
        'token' => Str::random(64),
    ]);

    $response->assertStatus(400);
    $response->assertJson(['status' => 'error']);
});

test('confirm email change updates email with valid token', function (): void {
    $token = Str::random(64);
    $this->user->update([
        'pending_email' => 'new@example.com',
        'email_change_token' => $token,
        'email_change_requested_at' => now(),
    ]);

    $response = postJson('/api/v1/email/change/confirm', [
        'token' => $token,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'success', 'email' => 'new@example.com']);

    $this->user->refresh();
    expect($this->user->email)->toBe('new@example.com');
    expect($this->user->pending_email)->toBeNull();
    expect($this->user->email_change_token)->toBeNull();
    expect($this->user->email_verified_at)->not()->toBeNull();
});

test('confirm email change rejects expired token', function (): void {
    $token = Str::random(64);
    $this->user->update([
        'pending_email' => 'new@example.com',
        'email_change_token' => $token,
        'email_change_requested_at' => now()->subHours(25),
    ]);

    $response = postJson('/api/v1/email/change/confirm', [
        'token' => $token,
    ]);

    $response->assertStatus(400);

    $this->user->refresh();
    expect($this->user->email)->toBe('original@example.com');
    expect($this->user->pending_email)->toBeNull();
});

test('confirm email change creates moderation log', function (): void {
    $token = Str::random(64);
    $this->user->update([
        'pending_email' => 'new@example.com',
        'email_change_token' => $token,
        'email_change_requested_at' => now(),
    ]);

    postJson('/api/v1/email/change/confirm', [
        'token' => $token,
    ]);

    $log = ModerationLog::where('action', 'email_changed')
        ->where('target_user_id', $this->user->id)
        ->first();

    expect($log)->not()->toBeNull();
    expect($log->metadata['old_email'])->toBe('original@example.com');
    expect($log->metadata['new_email'])->toBe('new@example.com');
});

// Cancel tests
test('cancel email change requires authentication', function (): void {
    $response = deleteJson('/api/v1/user/email/change');

    $response->assertStatus(401);
});

test('cancel email change clears pending email', function (): void {
    $this->user->update([
        'pending_email' => 'new@example.com',
        'email_change_token' => Str::random(64),
        'email_change_requested_at' => now(),
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson('/api/v1/user/email/change');

    $response->assertStatus(200);

    $this->user->refresh();
    expect($this->user->pending_email)->toBeNull();
    expect($this->user->email_change_token)->toBeNull();
    expect($this->user->email_change_requested_at)->toBeNull();
});

test('cancel email change fails when no pending change', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson('/api/v1/user/email/change');

    $response->assertStatus(400);
});

// Status tests
test('status requires authentication', function (): void {
    $response = getJson('/api/v1/user/email/change/status');

    $response->assertStatus(401);
});

test('status returns no pending change when none exists', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/user/email/change/status');

    $response->assertStatus(200);
    $response->assertJson(['has_pending_change' => false]);
});

test('status returns pending change details', function (): void {
    $this->user->update([
        'pending_email' => 'new@example.com',
        'email_change_token' => Str::random(64),
        'email_change_requested_at' => now(),
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/user/email/change/status');

    $response->assertStatus(200);
    $response->assertJson([
        'has_pending_change' => true,
        'pending_email' => 'new@example.com',
        'is_expired' => false,
    ]);
});

test('status indicates expired token', function (): void {
    $this->user->update([
        'pending_email' => 'new@example.com',
        'email_change_token' => Str::random(64),
        'email_change_requested_at' => now()->subHours(25),
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/user/email/change/status');

    $response->assertStatus(200);
    $response->assertJson([
        'has_pending_change' => true,
        'is_expired' => true,
    ]);
});
