<?php

declare(strict_types=1);

use App\Models\Sub;
use App\Models\SubModerator;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->owner = User::factory()->create([
        'karma_points' => 5000,
        'highest_level_id' => 4,
        'created_at' => now()->subDays(40),
    ]);

    $this->member = User::factory()->create([
        'karma_points' => 1000,
        'highest_level_id' => 2,
        'created_at' => now()->subDays(30),
    ]);

    $this->moderator = User::factory()->create([
        'karma_points' => 3000,
        'highest_level_id' => 3,
        'created_at' => now()->subDays(35),
    ]);

    $this->sub = Sub::create([
        'name' => 'test-orphaned',
        'display_name' => 'Test Orphaned Sub',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 3,
    ]);

    // Add owner, moderator and member as subscribers
    $this->sub->subscribers()->attach($this->owner->id);
    $this->sub->subscribers()->attach($this->moderator->id);
    $this->sub->subscribers()->attach($this->member->id);

    // Add moderator to sub_moderators
    SubModerator::create([
        'sub_id' => $this->sub->id,
        'user_id' => $this->moderator->id,
        'is_owner' => false,
        'added_by' => $this->owner->id,
    ]);
});

// === Model Tests ===

test('isOrphaned returns false when owner is a member', function (): void {
    expect($this->sub->isOrphaned())->toBeFalse();
});

test('isOrphaned returns true when owner has left the sub', function (): void {
    // Owner leaves the sub
    $this->sub->subscribers()->detach($this->owner->id);

    expect($this->sub->isOrphaned())->toBeTrue();
});

test('canClaimOwnership returns false for non-members', function (): void {
    // Owner leaves
    $this->sub->subscribers()->detach($this->owner->id);

    $nonMember = User::factory()->create();

    expect($this->sub->canClaimOwnership($nonMember))->toBeFalse();
});

test('canClaimOwnership returns true for moderator when orphaned', function (): void {
    // Owner leaves
    $this->sub->subscribers()->detach($this->owner->id);

    expect($this->sub->canClaimOwnership($this->moderator))->toBeTrue();
});

test('canClaimOwnership returns false for regular member when moderators exist', function (): void {
    // Owner leaves
    $this->sub->subscribers()->detach($this->owner->id);

    // canClaimOwnership checks membership AND moderator priority
    // Regular members cannot claim when active moderators exist and grace period hasn't expired
    expect($this->sub->canClaimOwnership($this->member))->toBeFalse();
});

test('hasClaimPriority returns true for moderator', function (): void {
    expect($this->sub->hasClaimPriority($this->moderator))->toBeTrue();
});

test('hasClaimPriority returns false for regular member', function (): void {
    expect($this->sub->hasClaimPriority($this->member))->toBeFalse();
});

test('getActiveModeratorCount returns correct count', function (): void {
    expect($this->sub->getActiveModeratorCount())->toBe(1);

    // Add another moderator
    $anotherMod = User::factory()->create();
    $this->sub->subscribers()->attach($anotherMod->id);
    SubModerator::create([
        'sub_id' => $this->sub->id,
        'user_id' => $anotherMod->id,
        'is_owner' => false,
        'added_by' => $this->owner->id,
    ]);

    expect($this->sub->getActiveModeratorCount())->toBe(2);
});

// === Endpoint Tests ===

test('show includes is_orphaned flag', function (): void {
    $response = getJson("/api/v1/subs/{$this->sub->name}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.is_orphaned', false);
});

test('show includes is_orphaned true when owner left', function (): void {
    $this->sub->subscribers()->detach($this->owner->id);

    $response = getJson("/api/v1/subs/{$this->sub->name}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.is_orphaned', true);
});

test('show includes can_claim and has_claim_priority for authenticated users', function (): void {
    $this->sub->subscribers()->detach($this->owner->id);

    Sanctum::actingAs($this->moderator);
    $response = getJson("/api/v1/subs/{$this->sub->name}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.can_claim', true);
    $response->assertJsonPath('data.has_claim_priority', true);
});

test('claimStatus endpoint returns claim info', function (): void {
    $this->sub->subscribers()->detach($this->owner->id);

    Sanctum::actingAs($this->moderator);
    $response = getJson("/api/v1/subs/{$this->sub->id}/claim-status");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'is_orphaned',
        'can_claim',
        'has_priority',
        'active_moderators',
    ]);
    $response->assertJsonPath('is_orphaned', true);
    $response->assertJsonPath('can_claim', true);
    $response->assertJsonPath('has_priority', true);
});

test('claimOwnership fails for non-orphaned sub', function (): void {
    Sanctum::actingAs($this->moderator);

    $response = postJson("/api/v1/subs/{$this->sub->id}/claim");

    $response->assertStatus(400);
    $response->assertJsonStructure(['error', 'message']);
    expect($response->json('error'))->toBe('Not orphaned');
});

test('claimOwnership fails for non-member', function (): void {
    $this->sub->subscribers()->detach($this->owner->id);

    $nonMember = User::factory()->create();
    Sanctum::actingAs($nonMember);

    $response = postJson("/api/v1/subs/{$this->sub->id}/claim");

    $response->assertStatus(403);
    expect($response->json('error'))->toBe('Cannot claim');
});

test('claimOwnership fails for regular member when moderators exist', function (): void {
    $this->sub->subscribers()->detach($this->owner->id);

    Sanctum::actingAs($this->member);

    $response = postJson("/api/v1/subs/{$this->sub->id}/claim");

    $response->assertStatus(403);
    expect($response->json('error'))->toBe('Cannot claim');
});

test('claimOwnership succeeds for moderator', function (): void {
    $this->sub->subscribers()->detach($this->owner->id);

    Sanctum::actingAs($this->moderator);

    $response = postJson("/api/v1/subs/{$this->sub->id}/claim");

    $response->assertStatus(200);
    $response->assertJsonStructure(['message', 'data']);

    // Verify ownership transfer
    $this->sub->refresh();
    expect($this->sub->created_by)->toBe($this->moderator->id);

    // Verify moderator entry updated to owner
    $modEntry = SubModerator::where('sub_id', $this->sub->id)
        ->where('user_id', $this->moderator->id)
        ->first();
    expect($modEntry->is_owner)->toBeTrue();
});

test('claimOwnership succeeds for regular member when no moderators', function (): void {
    $this->sub->subscribers()->detach($this->owner->id);
    SubModerator::where('sub_id', $this->sub->id)->delete();

    Sanctum::actingAs($this->member);

    $response = postJson("/api/v1/subs/{$this->sub->id}/claim");

    $response->assertStatus(200);
    $response->assertJsonStructure(['message', 'data']);

    // Verify ownership transfer
    $this->sub->refresh();
    expect($this->sub->created_by)->toBe($this->member->id);

    // Verify new moderator entry created
    $modEntry = SubModerator::where('sub_id', $this->sub->id)
        ->where('user_id', $this->member->id)
        ->first();
    expect($modEntry)->not->toBeNull();
    expect($modEntry->is_owner)->toBeTrue();
});

test('claimOwnership requires authentication', function (): void {
    $this->sub->subscribers()->detach($this->owner->id);

    $response = postJson("/api/v1/subs/{$this->sub->id}/claim");

    $response->assertStatus(401);
});

test('claimStatus requires authentication', function (): void {
    $response = getJson("/api/v1/subs/{$this->sub->id}/claim-status");

    $response->assertStatus(401);
});
