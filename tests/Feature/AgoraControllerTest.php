<?php

declare(strict_types=1);

use App\Models\AgoraMessage;
use App\Models\AgoraVote;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

// Index tests
test('index returns top level messages', function (): void {
    AgoraMessage::factory()->count(3)->create(['parent_id' => null]);

    $response = getJson('/api/v1/agora');

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
});

test('index returns messages ordered chronologically descending', function (): void {
    $first = AgoraMessage::factory()->create(['parent_id' => null, 'created_at' => now()->subDays(2)]);
    $second = AgoraMessage::factory()->create(['parent_id' => null, 'created_at' => now()->subDay()]);
    $third = AgoraMessage::factory()->create(['parent_id' => null, 'created_at' => now()]);

    $response = getJson('/api/v1/agora');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data[0]['id'])->toBe($third->id);
    expect($data[1]['id'])->toBe($second->id);
    expect($data[2]['id'])->toBe($first->id);
});

test('index does not include child messages', function (): void {
    $parent = AgoraMessage::factory()->create(['parent_id' => null]);
    AgoraMessage::factory()->create(['parent_id' => $parent->id]);

    $response = getJson('/api/v1/agora');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
});

test('index includes reply count', function (): void {
    $parent = AgoraMessage::factory()->create(['parent_id' => null]);
    AgoraMessage::factory()->count(2)->create(['parent_id' => $parent->id]);

    $response = getJson('/api/v1/agora');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.replies_count', 2);
});

test('index accepts per_page parameter', function (): void {
    AgoraMessage::factory()->count(5)->create(['parent_id' => null]);

    $response = getJson('/api/v1/agora?per_page=2');

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
});

test('index limits per_page to maximum 100', function (): void {
    AgoraMessage::factory()->count(5)->create(['parent_id' => null]);

    $response = getJson('/api/v1/agora?per_page=200');

    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
});

test('index includes user vote when authenticated', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create(['parent_id' => null]);
    AgoraVote::create([
        'user_id' => $this->user->id,
        'agora_message_id' => $message->id,
        'value' => 1,
    ]);

    $response = getJson('/api/v1/agora');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.user_vote', 1);
});

// Show tests
test('show returns message by id', function (): void {
    $message = AgoraMessage::factory()->create();

    $response = getJson("/api/v1/agora/{$message->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.id', $message->id);
});

test('show returns 404 for non-existent message', function (): void {
    $response = getJson('/api/v1/agora/99999');

    $response->assertStatus(404);
});

test('show includes user vote when authenticated', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create();
    AgoraVote::create([
        'user_id' => $this->user->id,
        'agora_message_id' => $message->id,
        'value' => -1,
    ]);

    $response = getJson("/api/v1/agora/{$message->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.user_vote', -1);
});

// Store tests
test('store requires authentication', function (): void {
    $response = postJson('/api/v1/agora', [
        'content' => 'Test message',
    ]);

    $response->assertStatus(401);
});

test('store creates new message', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/agora', [
        'content' => 'Test message content',
        'expires_in_hours' => 24,
    ]);

    $response->assertStatus(201);
    expect(AgoraMessage::where('content', 'Test message content')->exists())->toBeTrue();
});

test('store requires content', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/agora', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content']);
});

test('store validates content maximum 5000 characters', function (): void {
    Sanctum::actingAs($this->user);

    $longContent = str_repeat('A', 5001);
    $response = postJson('/api/v1/agora', [
        'content' => $longContent,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content']);
});

test('store accepts parent_id for replies', function (): void {
    Sanctum::actingAs($this->user);

    $parent = AgoraMessage::factory()->create();

    $response = postJson('/api/v1/agora', [
        'content' => 'Reply message',
        'parent_id' => $parent->id,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.parent_id', $parent->id);
});

test('store validates that parent_id exists', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/agora', [
        'content' => 'Reply message',
        'parent_id' => 99999,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['parent_id']);
});

test('store accepts is_anonymous', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/agora', [
        'content' => 'Anonymous message',
        'is_anonymous' => true,
        'expires_in_hours' => 24,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.is_anonymous', true);
});

test('store uses is_anonymous false by default', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/agora', [
        'content' => 'Public message',
        'expires_in_hours' => 24,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.is_anonymous', false);
});

test('store accepts language_code', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/agora', [
        'content' => 'English message',
        'language_code' => 'en',
        'expires_in_hours' => 24,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.language_code', 'en');
});

test('store validates language_code size 2', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/agora', [
        'content' => 'Message',
        'language_code' => 'eng',
        'expires_in_hours' => 24,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['language_code']);
});

test('store uses es as default language_code', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/agora', [
        'content' => 'Mensaje en español',
        'expires_in_hours' => 24,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.language_code', 'es');
});

// Update tests
test('update requires authentication', function (): void {
    $message = AgoraMessage::factory()->create();

    $response = putJson("/api/v1/agora/{$message->id}", [
        'content' => 'Updated content',
    ]);

    $response->assertStatus(401);
});

test('update updates message', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create(['user_id' => $this->user->id]);

    $response = putJson("/api/v1/agora/{$message->id}", [
        'content' => 'Updated content',
    ]);

    $response->assertStatus(200);
    expect($message->fresh()->content)->toBe('Updated content');
});

test('update returns 404 for non-existent message', function (): void {
    Sanctum::actingAs($this->user);

    $response = putJson('/api/v1/agora/99999', [
        'content' => 'Updated content',
    ]);

    $response->assertStatus(404);
});

test('update only allows author', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create(['user_id' => $this->otherUser->id]);

    $response = putJson("/api/v1/agora/{$message->id}", [
        'content' => 'Updated content',
    ]);

    $response->assertStatus(403);
});

test('update requires content', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create(['user_id' => $this->user->id]);

    $response = putJson("/api/v1/agora/{$message->id}", []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content']);
});

// Destroy tests
test('destroy requires authentication', function (): void {
    $message = AgoraMessage::factory()->create();

    $response = deleteJson("/api/v1/agora/{$message->id}");

    $response->assertStatus(401);
});

test('destroy deletes author message', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create(['user_id' => $this->user->id]);

    $response = deleteJson("/api/v1/agora/{$message->id}");

    $response->assertStatus(200);
    expect(AgoraMessage::find($message->id))->toBeNull();
});

test('destroy returns 404 for non-existent message', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson('/api/v1/agora/99999');

    $response->assertStatus(404);
});

test('destroy only allows author if not admin', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create(['user_id' => $this->otherUser->id]);

    $response = deleteJson("/api/v1/agora/{$message->id}");

    $response->assertStatus(403);
});

test('destroy allows admin to delete any message', function (): void {
    $adminRole = Role::firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Administrator', 'display_name' => 'Administrator'],
    );

    $admin = User::factory()->create();
    $admin->roles()->attach($adminRole);

    Sanctum::actingAs($admin);

    $message = AgoraMessage::factory()->create(['user_id' => $this->otherUser->id]);

    $response = deleteJson("/api/v1/agora/{$message->id}");

    $response->assertStatus(200);
    expect(AgoraMessage::find($message->id))->toBeNull();
});

// Vote tests
test('vote requires authentication', function (): void {
    $message = AgoraMessage::factory()->create();

    $response = postJson("/api/v1/agora/{$message->id}/vote", [
        'value' => 1,
    ]);

    $response->assertStatus(401);
});

test('vote creates upvote', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create();

    $response = postJson("/api/v1/agora/{$message->id}/vote", [
        'value' => 1,
    ]);

    $response->assertStatus(200);
    expect(AgoraVote::where('user_id', $this->user->id)
        ->where('agora_message_id', $message->id)
        ->where('value', 1)
        ->exists())->toBeTrue();
});

test('vote creates downvote', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create();

    $response = postJson("/api/v1/agora/{$message->id}/vote", [
        'value' => -1,
    ]);

    $response->assertStatus(200);
    expect(AgoraVote::where('user_id', $this->user->id)
        ->where('agora_message_id', $message->id)
        ->where('value', -1)
        ->exists())->toBeTrue();
});

test('vote requires value', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create();

    $response = postJson("/api/v1/agora/{$message->id}/vote", []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['value']);
});

test('vote validates value only accepts -1 or 1', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create();

    $response = postJson("/api/v1/agora/{$message->id}/vote", [
        'value' => 2,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['value']);
});

test('vote returns 404 for non-existent message', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/agora/99999/vote', [
        'value' => 1,
    ]);

    $response->assertStatus(404);
});

test('vote updates existing vote', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create();

    AgoraVote::create([
        'user_id' => $this->user->id,
        'agora_message_id' => $message->id,
        'value' => 1,
    ]);

    $response = postJson("/api/v1/agora/{$message->id}/vote", [
        'value' => -1,
    ]);

    $response->assertStatus(200);
    $vote = AgoraVote::where('user_id', $this->user->id)
        ->where('agora_message_id', $message->id)
        ->first();
    expect($vote->value)->toBe(-1);
});

test('vote accepts optional fingerprint', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create();

    $response = postJson("/api/v1/agora/{$message->id}/vote", [
        'value' => 1,
        'fingerprint' => 'abc123',
    ]);

    $response->assertStatus(200);
    $vote = AgoraVote::where('user_id', $this->user->id)
        ->where('agora_message_id', $message->id)
        ->first();
    expect($vote->fingerprint)->toBe('abc123');
});

// Unvote tests
test('unvote requires authentication', function (): void {
    $message = AgoraMessage::factory()->create();

    $response = deleteJson("/api/v1/agora/{$message->id}/vote");

    $response->assertStatus(401);
});

test('unvote deletes vote', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create();

    AgoraVote::create([
        'user_id' => $this->user->id,
        'agora_message_id' => $message->id,
        'value' => 1,
    ]);

    $response = deleteJson("/api/v1/agora/{$message->id}/vote");

    $response->assertStatus(200);
    expect(AgoraVote::where('user_id', $this->user->id)
        ->where('agora_message_id', $message->id)
        ->exists())->toBeFalse();
});

test('unvote returns 404 for non-existent message', function (): void {
    Sanctum::actingAs($this->user);

    $response = deleteJson('/api/v1/agora/99999/vote');

    $response->assertStatus(404);
});

test('unvote works even without previous vote', function (): void {
    Sanctum::actingAs($this->user);

    $message = AgoraMessage::factory()->create();

    $response = deleteJson("/api/v1/agora/{$message->id}/vote");

    $response->assertStatus(200);
});
