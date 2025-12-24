<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

test('index returns user invitations', function (): void {
    Invitation::factory()->count(3)->create(['created_by' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/invitations');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'invitations' => [
            '*' => ['id', 'code', 'max_uses', 'current_uses', 'is_active', 'created_at'],
        ],
        'limit',
        'used',
        'remaining',
        'can_create',
    ]);
    expect(count($response->json('invitations')))->toBe(3);
});

test('index requires authentication', function (): void {
    $response = getJson('/api/v1/invitations');

    $response->assertStatus(401);
});

test('index does not show other user invitations', function (): void {
    $otherUser = User::factory()->create();
    Invitation::factory()->count(2)->create(['created_by' => $otherUser->id]);
    Invitation::factory()->create(['created_by' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/invitations');

    $response->assertStatus(200);
    expect(count($response->json('invitations')))->toBe(1);
});

test('index includes registration URL', function (): void {
    Invitation::factory()->create(['created_by' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/invitations');

    $response->assertStatus(200);
    $invitations = $response->json('invitations');
    expect($invitations[0])->toHaveKey('registration_url');
    expect($invitations[0]['registration_url'])->toContain('/auth/register?invitation=');
});

test('index orders invitations by date descending', function (): void {
    $old = Invitation::factory()->create([
        'code' => 'OLD123',
        'created_by' => $this->user->id,
    ]);

    // Ensure the old invitation has an older timestamp
    $old->created_at = now()->subDays(2);
    $old->save();

    sleep(1); // Ensure different timestamps

    $new = Invitation::factory()->create([
        'code' => 'NEW456',
        'created_by' => $this->user->id,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/invitations');

    $response->assertStatus(200);
    $invitations = $response->json('invitations');

    // Verify newest is first
    expect($invitations[0]['code'])->toBe('NEW456');
    expect($invitations[1]['code'])->toBe('OLD123');
});

test('store creates new invitation', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/invitations');

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'invitation' => ['id', 'code', 'max_uses', 'registration_url'],
        'remaining',
        'message',
    ]);
    $response->assertJsonPath('message', 'Invitation created successfully.');

    expect(Invitation::where('created_by', $this->user->id)->exists())->toBeTrue();
});

test('store requires authentication', function (): void {
    $response = postJson('/api/v1/invitations');

    $response->assertStatus(401);
});

test('store accepts custom max_uses', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/invitations', [
        'max_uses' => 5,
    ]);

    $response->assertStatus(201);
    $invitation = Invitation::where('created_by', $this->user->id)->first();
    expect($invitation->max_uses)->toBe(5);
});

test('store accepts custom expires_in_days', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/invitations', [
        'expires_in_days' => 7,
    ]);

    $response->assertStatus(201);
    $invitation = Invitation::where('created_by', $this->user->id)->first();
    expect($invitation->expires_at)->not->toBeNull();
});

test('store validates minimum max_uses', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/invitations', [
        'max_uses' => 0,
    ]);

    $response->assertStatus(422);
});

test('store validates maximum max_uses', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/invitations', [
        'max_uses' => 11,
    ]);

    $response->assertStatus(422);
});

test('store validates minimum expires_in_days', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/invitations', [
        'expires_in_days' => 0,
    ]);

    $response->assertStatus(422);
});

test('store validates maximum expires_in_days', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/invitations', [
        'expires_in_days' => 366,
    ]);

    $response->assertStatus(422);
});

test('store generates unique code', function (): void {
    Sanctum::actingAs($this->user);

    $response1 = postJson('/api/v1/invitations');
    $response2 = postJson('/api/v1/invitations');

    $response1->assertStatus(201);
    $response2->assertStatus(201);

    $code1 = $response1->json('invitation.code');
    $code2 = $response2->json('invitation.code');

    expect($code1)->not->toBe($code2);
});

test('destroy deletes invitation', function (): void {
    $invitation = Invitation::factory()->create([
        'created_by' => $this->user->id,
        'current_uses' => 0,
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/invitations/{$invitation->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('message', 'Invitation deleted successfully.');

    expect(Invitation::find($invitation->id))->toBeNull();
});

test('destroy requires authentication', function (): void {
    $invitation = Invitation::factory()->create(['created_by' => $this->user->id]);

    $response = deleteJson("/api/v1/invitations/{$invitation->id}");

    $response->assertStatus(401);
});

test('destroy does not allow deleting another users invitation', function (): void {
    $otherUser = User::factory()->create();
    $invitation = Invitation::factory()->create(['created_by' => $otherUser->id]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/invitations/{$invitation->id}");

    $response->assertStatus(403);
    $response->assertJsonPath('message', 'You do not have permission to delete this invitation.');
});

test('destroy does not allow deleting already used invitation', function (): void {
    $invitation = Invitation::factory()->create([
        'created_by' => $this->user->id,
        'current_uses' => 1,
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/invitations/{$invitation->id}");

    $response->assertStatus(403);
    $response->assertJsonPath('message', 'Cannot delete an invitation that has been used.');

    expect(Invitation::find($invitation->id))->not->toBeNull();
});

test('index shows active and inactive invitations', function (): void {
    Invitation::factory()->create([
        'created_by' => $this->user->id,
        'is_active' => true,
    ]);

    Invitation::factory()->create([
        'created_by' => $this->user->id,
        'is_active' => false,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/invitations');

    $response->assertStatus(200);
    expect(count($response->json('invitations')))->toBe(2);
});

test('index includes limit information', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/invitations');

    $response->assertStatus(200);
    expect($response->json())->toHaveKey('limit');
    expect($response->json())->toHaveKey('used');
    expect($response->json())->toHaveKey('remaining');
    expect($response->json())->toHaveKey('can_create');
});

test('store includes remaining in response', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/invitations');

    $response->assertStatus(201);
    expect($response->json())->toHaveKey('remaining');
});

test('index shows current_uses correctly', function (): void {
    Invitation::factory()->create([
        'created_by' => $this->user->id,
        'current_uses' => 2,
        'max_uses' => 5,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/invitations');

    $response->assertStatus(200);
    $invitations = $response->json('invitations');
    expect($invitations[0]['current_uses'])->toBe(2);
    expect($invitations[0]['max_uses'])->toBe(5);
});
