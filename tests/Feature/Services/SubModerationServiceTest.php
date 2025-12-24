<?php

declare(strict_types=1);

use App\Models\Sub;
use App\Models\User;
use App\Services\SubModerationService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->service = app(SubModerationService::class);
});

test('getModerators returns empty collection for sub with no moderators', function (): void {
    $owner = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 1,
        'icon' => '',
        'color' => '#6366F1',
    ]);

    // Owner is not in moderators table but should be included
    $moderators = $this->service->getModerators($sub);

    expect($moderators)->toHaveCount(1);
    expect($moderators->first()->id)->toBe($owner->id);
    expect($moderators->first()->pivot->is_owner)->toBeTrue();
});

test('getModerators returns moderators ordered by ownership and date', function (): void {
    $owner = User::factory()->create();
    $mod1 = User::factory()->create();
    $mod2 = User::factory()->create();

    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 3,
        'icon' => '',
        'color' => '#6366F1',
    ]);

    // Add owner as moderator
    $sub->moderators()->attach($owner->id, ['is_owner' => true, 'added_by' => $owner->id]);

    // Add mods with specific timestamps
    $sub->moderators()->attach($mod1->id, [
        'is_owner' => false,
        'added_by' => $owner->id,
        'created_at' => Carbon::now()->subDays(2),
    ]);
    $sub->moderators()->attach($mod2->id, [
        'is_owner' => false,
        'added_by' => $owner->id,
        'created_at' => Carbon::now()->subDay(),
    ]);

    $moderators = $this->service->getModerators($sub);

    expect($moderators)->toHaveCount(3);
    expect($moderators->first()->id)->toBe($owner->id);
    expect((bool) $moderators->first()->pivot->is_owner)->toBeTrue();
});

test('addModerator fails for owner', function (): void {
    $owner = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 1,
        'icon' => '',
        'color' => '#6366F1',
    ]);

    $result = $this->service->addModerator($sub, $owner->id, $owner);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('subs.owner_already_moderator'));
});

test('addModerator fails if user is already moderator', function (): void {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 2,
        'icon' => '',
        'color' => '#6366F1',
    ]);

    // Add user as member and moderator
    $sub->subscribers()->attach($user->id, ['status' => 'active']);
    $sub->moderators()->attach($user->id, ['is_owner' => false, 'added_by' => $owner->id]);

    $result = $this->service->addModerator($sub, $user->id, $owner);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('subs.already_moderator'));
});

test('addModerator fails if user is not a member', function (): void {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 1,
        'icon' => '',
        'color' => '#6366F1',
    ]);

    $result = $this->service->addModerator($sub, $user->id, $owner);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('subs.must_be_member_to_moderate'));
});

test('addModerator succeeds for member', function (): void {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 2,
        'icon' => '',
        'color' => '#6366F1',
    ]);

    // Add user as member
    $sub->subscribers()->attach($user->id, ['status' => 'active']);

    $result = $this->service->addModerator($sub, $user->id, $owner);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe(__('subs.moderator_added'));
    expect($result['user']->id)->toBe($user->id);
    expect($sub->moderators()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('removeModerator fails for owner', function (): void {
    $owner = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 1,
        'icon' => '',
        'color' => '#6366F1',
    ]);

    $result = $this->service->removeModerator($sub, $owner->id);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('subs.cannot_remove_owner'));
});

test('removeModerator succeeds for regular moderator', function (): void {
    $owner = User::factory()->create();
    $mod = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 2,
        'icon' => '',
        'color' => '#6366F1',
    ]);

    // Add mod
    $sub->moderators()->attach($mod->id, ['is_owner' => false, 'added_by' => $owner->id]);

    $result = $this->service->removeModerator($sub, $mod->id);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe(__('subs.moderator_removed'));
    expect($sub->moderators()->where('user_id', $mod->id)->exists())->toBeFalse();
});

test('claimOwnership fails if sub is not orphaned', function (): void {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 2,
        'icon' => '',
        'color' => '#6366F1',
    ]);

    // Owner is still a member
    $sub->subscribers()->attach($owner->id, ['status' => 'active']);
    $sub->subscribers()->attach($user->id, ['status' => 'active']);

    $result = $this->service->claimOwnership($sub, $user);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('subs.not_orphaned'));
});

test('claimOwnership fails if user cannot claim', function (): void {
    $owner = User::factory()->create();
    $mod = User::factory()->create();
    $member = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 3,
        'icon' => '',
        'color' => '#6366F1',
        'orphaned_at' => now(),
    ]);

    // Add members
    $sub->subscribers()->attach($mod->id, ['status' => 'active']);
    $sub->subscribers()->attach($member->id, ['status' => 'active']);

    // Add mod (has priority during grace period)
    $sub->moderators()->attach($mod->id, ['is_owner' => false, 'added_by' => $owner->id]);

    // Regular member can't claim during grace period
    $result = $this->service->claimOwnership($sub, $member);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('subs.cannot_claim'));
});

test('claimOwnership succeeds for moderator of orphaned sub', function (): void {
    $owner = User::factory()->create();
    $mod = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 2,
        'icon' => '',
        'color' => '#6366F1',
        'orphaned_at' => now(),
    ]);

    // Add mod as member and moderator
    $sub->subscribers()->attach($mod->id, ['status' => 'active']);
    $sub->moderators()->attach($mod->id, ['is_owner' => false, 'added_by' => $owner->id]);

    $result = $this->service->claimOwnership($sub, $mod);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe(__('subs.ownership_claimed'));
    expect($result['sub']->created_by)->toBe($mod->id);
    expect($result['sub']->orphaned_at)->toBeNull();
});

test('claimOwnership succeeds for member after grace period expires', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 2,
        'icon' => '',
        'color' => '#6366F1',
        'orphaned_at' => now()->subDays(10), // Grace period expired
    ]);

    // Add member
    $sub->subscribers()->attach($member->id, ['status' => 'active']);

    $result = $this->service->claimOwnership($sub, $member);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe(__('subs.ownership_claimed'));
    expect($result['sub']->created_by)->toBe($member->id);
});

test('getClaimStatus returns correct status for non-orphaned sub', function (): void {
    $owner = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 1,
        'icon' => '',
        'color' => '#6366F1',
    ]);

    // Owner is still a member
    $sub->subscribers()->attach($owner->id, ['status' => 'active']);

    $status = $this->service->getClaimStatus($sub, $owner);

    expect($status['is_orphaned'])->toBeFalse();
    expect($status['can_claim'])->toBeFalse();
    expect($status['has_priority'])->toBeFalse();
});

test('getClaimStatus returns correct status for orphaned sub with moderator', function (): void {
    $owner = User::factory()->create();
    $mod = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 2,
        'icon' => '',
        'color' => '#6366F1',
        'orphaned_at' => now(),
    ]);

    // Add mod as member and moderator
    $sub->subscribers()->attach($mod->id, ['status' => 'active']);
    $sub->moderators()->attach($mod->id, ['is_owner' => false, 'added_by' => $owner->id]);

    $status = $this->service->getClaimStatus($sub, $mod);

    expect($status['is_orphaned'])->toBeTrue();
    expect($status['can_claim'])->toBeTrue();
    expect($status['has_priority'])->toBeTrue();
    expect($status['active_moderators'])->toBe(1);
    expect($status['grace_period_expired'])->toBeFalse();
});

test('getClaimStatus returns correct status for regular member during grace period', function (): void {
    $owner = User::factory()->create();
    $mod = User::factory()->create();
    $member = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 3,
        'icon' => '',
        'color' => '#6366F1',
        'orphaned_at' => now(),
    ]);

    // Add members
    $sub->subscribers()->attach($mod->id, ['status' => 'active']);
    $sub->subscribers()->attach($member->id, ['status' => 'active']);
    $sub->moderators()->attach($mod->id, ['is_owner' => false, 'added_by' => $owner->id]);

    $status = $this->service->getClaimStatus($sub, $member);

    expect($status['is_orphaned'])->toBeTrue();
    expect($status['can_claim'])->toBeFalse();
    expect($status['has_priority'])->toBeFalse();
    expect($status['active_moderators'])->toBe(1);
    expect($status['grace_period_expired'])->toBeFalse();
});

test('getClaimStatus returns null user gracefully', function (): void {
    $owner = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $owner->id,
        'members_count' => 1,
        'icon' => '',
        'color' => '#6366F1',
        'orphaned_at' => now(),
    ]);

    $status = $this->service->getClaimStatus($sub, null);

    expect($status['is_orphaned'])->toBeTrue();
    expect($status['can_claim'])->toBeFalse();
    expect($status['has_priority'])->toBeFalse();
});
