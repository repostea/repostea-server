<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\SpamDetection;
use App\Models\User;

beforeEach(function (): void {
    // Create roles
    Role::create([
        'name' => 'admin',
        'slug' => 'admin',
        'display_name' => 'Administrator',
        'description' => 'Administrator role for testing',
    ]);

    Role::create([
        'name' => 'moderator',
        'slug' => 'moderator',
        'display_name' => 'Moderator',
        'description' => 'Moderator role for testing',
    ]);
});

test('non-authenticated users cannot access spam logs', function (): void {
    $response = $this->get(route('admin.spam-logs'));

    $response->assertRedirect(route('admin.login'));
});

test('non-admin users cannot access spam logs', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.spam-logs'));

    $response->assertForbidden();
});

test('admin can view spam logs page', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.spam-logs'));

    $response->assertOk();
    $response->assertViewIs('admin.spam-logs');
});

test('moderator can view spam logs page', function (): void {
    $moderator = User::factory()->moderator()->create();

    $response = $this->actingAs($moderator)->get(route('admin.spam-logs'));

    $response->assertOk();
    $response->assertViewIs('admin.spam-logs');
});

test('spam logs page displays correct statistics', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    // Create detections
    SpamDetection::factory()->count(5)->create([
        'user_id' => $user->id,
        'reviewed' => false,
        'created_at' => now(),
    ]);

    SpamDetection::factory()->count(3)->create([
        'user_id' => $user->id,
        'reviewed' => true,
        'created_at' => now()->subDays(2),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.spam-logs'));

    $response->assertOk();
    $response->assertViewHas('stats', fn ($stats) => $stats['total'] === 8
            && $stats['pending_review'] === 5
            && $stats['today'] === 5
            && $stats['this_week'] === 8);
});

test('spam logs can be filtered by detection type', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    SpamDetection::factory()->create([
        'user_id' => $user->id,
        'detection_type' => 'duplicate',
    ]);

    SpamDetection::factory()->create([
        'user_id' => $user->id,
        'detection_type' => 'rapid_fire',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.spam-logs', ['detection_type' => 'duplicate']));

    $response->assertOk();
    $response->assertViewHas('detections', fn ($detections) => $detections->count() === 1 && $detections->first()->detection_type === 'duplicate');
});

test('spam logs can be filtered by reviewed status', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    SpamDetection::factory()->count(3)->create([
        'user_id' => $user->id,
        'reviewed' => false,
    ]);

    SpamDetection::factory()->count(2)->create([
        'user_id' => $user->id,
        'reviewed' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.spam-logs', ['reviewed' => '0']));

    $response->assertOk();
    $response->assertViewHas('detections', fn ($detections) => $detections->count() === 3);
});

test('spam logs can be filtered by time period', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    // Old detection
    SpamDetection::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(10),
    ]);

    // Recent detections
    SpamDetection::factory()->count(3)->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(2),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.spam-logs', ['days' => '7']));

    $response->assertOk();
    $response->assertViewHas('detections', fn ($detections) => $detections->count() === 3);
});

test('detections are paginated', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    SpamDetection::factory()->count(60)->create([
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.spam-logs'));

    $response->assertOk();
    $response->assertViewHas('detections', function ($detections) {
        return $detections->count() === 50; // Default pagination
    });
});

test('admin can mark detection as reviewed', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $detection = SpamDetection::factory()->create([
        'user_id' => $user->id,
        'reviewed' => false,
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.spam-detections.review', $detection->id), [
        'action' => 'ignored',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'message' => 'Detection marked as reviewed',
    ]);

    $detection->refresh();
    expect($detection->reviewed)->toBeTrue();
    expect($detection->reviewed_by)->toBe($admin->id);
    expect($detection->action_taken)->toBe('ignored');
    expect($detection->reviewed_at)->not->toBeNull();
});

test('moderator can mark detection as reviewed', function (): void {
    $moderator = User::factory()->moderator()->create();
    $user = User::factory()->create();

    $detection = SpamDetection::factory()->create([
        'user_id' => $user->id,
        'reviewed' => false,
    ]);

    $response = $this->actingAs($moderator)->postJson(route('admin.spam-detections.review', $detection->id), [
        'action' => 'deleted',
    ]);

    $response->assertOk();
    $detection->refresh();
    expect($detection->reviewed)->toBeTrue();
    expect($detection->reviewed_by)->toBe($moderator->id);
    expect($detection->action_taken)->toBe('deleted');
});

test('non-admin users cannot mark detection as reviewed', function (): void {
    $user = User::factory()->create();
    $spamUser = User::factory()->create();

    $detection = SpamDetection::factory()->create([
        'user_id' => $spamUser->id,
        'reviewed' => false,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.spam-detections.review', $detection->id), [
        'action' => 'ignored',
    ]);

    $response->assertForbidden();
});

test('spam logs displays empty state when no detections exist', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.spam-logs'));

    $response->assertOk();
    $response->assertSee('No spam detections found');
});

test('detections are ordered by creation date descending', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $old = SpamDetection::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(5),
    ]);

    $new = SpamDetection::factory()->create([
        'user_id' => $user->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.spam-logs'));

    $response->assertOk();
    $response->assertViewHas('detections', fn ($detections) => $detections->first()->id === $new->id);
});
