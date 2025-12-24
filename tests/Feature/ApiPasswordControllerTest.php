<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\putJson;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);
});

// update tests
test('update requires authentication', function (): void {
    $response = putJson('/api/v1/user/password', [
        'current_password' => 'current-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertStatus(401);
});

test('update requires current_password', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/user/password', [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['current_password']);
});

test('update requires password', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/user/password', [
        'current_password' => 'current-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

test('update requires password_confirmation', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/user/password', [
        'current_password' => 'current-password',
        'password' => 'new-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password_confirmation']);
});

test('update validates that current_password is correct', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/user/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['current_password']);
});

test('update validates that password and password_confirmation match', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/user/password', [
        'current_password' => 'current-password',
        'password' => 'new-password',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

test('update validates minimum password length', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/user/password', [
        'current_password' => 'current-password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

test('update updates password correctamente', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/user/password', [
        'current_password' => 'current-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertStatus(200);
    expect(Hash::check('new-password-123', $this->user->fresh()->password))->toBeTrue();
});

test('update returns success message', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/user/password', [
        'current_password' => 'current-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['message', 'status']);
    expect($response->json('status'))->toBe('success');
});

test('update hashes the new password', function (): void {
    Sanctum::actingAs($this->user);

    $newPassword = 'new-password-123';

    putJson('/api/v1/user/password', [
        'current_password' => 'current-password',
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]);

    $this->user->refresh();
    expect($this->user->password)->not()->toBe($newPassword);
    expect(Hash::check($newPassword, $this->user->password))->toBeTrue();
});

test('update does not update password if current_password is incorrect', function (): void {
    Sanctum::actingAs($this->user);

    $originalPassword = $this->user->password;

    putJson('/api/v1/user/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    expect($this->user->fresh()->password)->toBe($originalPassword);
});

test('update accepts complex passwords', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/user/password', [
        'current_password' => 'current-password',
        'password' => 'C0mpl3x-P@ssw0rd!',
        'password_confirmation' => 'C0mpl3x-P@ssw0rd!',
    ]);

    $response->assertStatus(200);
    expect(Hash::check('C0mpl3x-P@ssw0rd!', $this->user->fresh()->password))->toBeTrue();
});
