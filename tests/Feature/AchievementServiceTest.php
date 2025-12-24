<?php

declare(strict_types=1);

use App\Events\AchievementUnlocked as AchievementUnlockedEvent;
use App\Models\Achievement;
use App\Models\Sub;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->service = new AchievementService();
    $this->user = User::factory()->create(['karma_points' => 100]);
});

test('unlockIfExists unlocks existing achievement', function (): void {
    $achievement = Achievement::create([
        'slug' => 'first-post',
        'name' => 'First Post',
        'description' => 'Create your first post',
        'icon' => '📝',
        'type' => 'post',
        'karma_bonus' => 10,
        'requirements' => json_encode(['posts_count' => 1]),
    ]);

    Event::fake();

    $result = $this->service->unlockIfExists($this->user, 'first-post');

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($achievement->id);
    expect($this->user->achievements()->count())->toBe(1);

    $pivot = $this->user->achievements()->first()->pivot;
    expect($pivot->progress)->toBe(100);
    expect($pivot->unlocked_at)->not->toBeNull();

    Event::assertDispatched(AchievementUnlockedEvent::class);
});

test('unlockIfExists returns null for non-existent achievement', function (): void {
    $result = $this->service->unlockIfExists($this->user, 'non-existent');

    expect($result)->toBeNull();
});

test('unlock unlocks achievement and grants karma bonus', function (): void {
    $achievement = Achievement::create([
        'slug' => 'karma-achievement',
        'name' => 'Karma Achievement',
        'description' => 'Earn karma',
        'icon' => '⭐',
        'type' => 'karma',
        'karma_bonus' => 50,
        'requirements' => json_encode(['karma_points' => 100]),
    ]);

    Event::fake();

    $initialKarma = $this->user->karma_points;

    $result = $this->service->unlock($this->user, $achievement);

    expect($result->id)->toBe($achievement->id);

    $this->user->refresh();
    expect($this->user->karma_points)->toBe($initialKarma + 50);
    expect($this->user->achievements()->count())->toBe(1);

    Event::assertDispatched(AchievementUnlockedEvent::class);
});

test('unlock does not grant karma bonus if already unlocked', function (): void {
    $achievement = Achievement::create([
        'slug' => 'already-unlocked',
        'name' => 'Already Unlocked',
        'description' => 'Already unlocked achievement',
        'icon' => '🔓',
        'type' => 'special',
        'karma_bonus' => 50,
        'requirements' => json_encode(['test' => 1]),
    ]);

    // First unlock
    $this->service->unlock($this->user, $achievement);
    $this->user->refresh();
    $karmaAfterFirstUnlock = $this->user->karma_points;

    Event::fake();

    // Second unlock (should not give karma bonus)
    $this->service->unlock($this->user, $achievement);
    $this->user->refresh();

    expect($this->user->karma_points)->toBe($karmaAfterFirstUnlock);
    Event::assertNotDispatched(AchievementUnlockedEvent::class);
});

test('updateProgress incrementa progreso correctamente', function (): void {
    $achievement = Achievement::create([
        'slug' => 'progress-test',
        'name' => 'Progress Test',
        'description' => 'Test progress',
        'icon' => '📊',
        'type' => 'action',
        'karma_bonus' => 10,
        'requirements' => json_encode(['actions' => 10]),
    ]);

    $this->service->updateProgress($this->user, 'progress-test', 25);

    $pivot = $this->user->achievements()->where('achievement_id', $achievement->id)->first()->pivot;
    expect($pivot->progress)->toBe(25);
    expect($pivot->unlocked_at)->toBeNull();
});

test('updateProgress unlocks achievement when reaching 100', function (): void {
    $achievement = Achievement::create([
        'slug' => 'complete-test',
        'name' => 'Complete Test',
        'description' => 'Complete test',
        'icon' => '✅',
        'type' => 'action',
        'karma_bonus' => 20,
        'requirements' => json_encode(['actions' => 10]),
    ]);

    // Update to 60%
    $this->service->updateProgress($this->user, 'complete-test', 60);

    $this->user->refresh();
    $pivot = $this->user->achievements()->where('achievement_id', $achievement->id)->first()->pivot;
    expect($pivot->progress)->toBe(60);
    expect($pivot->unlocked_at)->toBeNull();

    $karmaBeforeUnlock = $this->user->karma_points;

    // Update to 100% (60 + 40) - this should trigger unlock
    $this->service->updateProgress($this->user, 'complete-test', 40);

    $this->user->refresh();
    $pivot = $this->user->achievements()->where('achievement_id', $achievement->id)->first()->pivot;
    expect($pivot->progress)->toBe(100);
    expect($pivot->unlocked_at)->not->toBeNull();

    // Verify karma bonus was NOT added (already existed in pivot table)
    expect($this->user->karma_points)->toBe($karmaBeforeUnlock);
});

test('updateProgress does not exceed 100 progress', function (): void {
    $achievement = Achievement::create([
        'slug' => 'max-progress',
        'name' => 'Max Progress',
        'description' => 'Max progress test',
        'icon' => '💯',
        'type' => 'action',
        'karma_bonus' => 10,
        'requirements' => json_encode(['actions' => 10]),
    ]);

    $this->service->updateProgress($this->user, 'max-progress', 150);

    $pivot = $this->user->achievements()->where('achievement_id', $achievement->id)->first()->pivot;
    expect($pivot->progress)->toBe(100);
});

test('updateProgress returns null for non-existent achievement', function (): void {
    $result = $this->service->updateProgress($this->user, 'non-existent', 50);

    expect($result)->toBeNull();
});

test('checkSubMemberAchievements unlocks member achievements', function (): void {
    $creator = User::factory()->create();

    $sub = Sub::create([
        'name' => 'popular-sub',
        'display_name' => 'Popular Sub',
        'created_by' => $creator->id,
        'members_count' => 100,
        'icon' => '🔥',
        'color' => '#FF0000',
    ]);

    // Create achievements for thresholds
    Achievement::create([
        'slug' => 'sub-members-10',
        'name' => '10 Members',
        'description' => 'Reach 10 members',
        'icon' => '👥',
        'type' => 'special',
        'karma_bonus' => 10,
        'requirements' => json_encode(['members' => 10]),
    ]);

    Achievement::create([
        'slug' => 'sub-members-50',
        'name' => '50 Members',
        'description' => 'Reach 50 members',
        'icon' => '👥',
        'type' => 'special',
        'karma_bonus' => 25,
        'requirements' => json_encode(['members' => 50]),
    ]);

    Achievement::create([
        'slug' => 'sub-members-100',
        'name' => '100 Members',
        'description' => 'Reach 100 members',
        'icon' => '👥',
        'type' => 'special',
        'karma_bonus' => 50,
        'requirements' => json_encode(['members' => 100]),
    ]);

    $this->service->checkSubMemberAchievements($sub);

    $creator->refresh();
    expect($creator->achievements()->count())->toBe(3);
});

test('checkSubMemberAchievements does not unlock below threshold', function (): void {
    $creator = User::factory()->create();

    $sub = Sub::create([
        'name' => 'small-sub',
        'display_name' => 'Small Sub',
        'created_by' => $creator->id,
        'members_count' => 5,
        'icon' => '📝',
        'color' => '#0000FF',
    ]);

    Achievement::create([
        'slug' => 'sub-members-10',
        'name' => '10 Members',
        'description' => 'Reach 10 members',
        'icon' => '👥',
        'type' => 'special',
        'karma_bonus' => 10,
        'requirements' => json_encode(['members' => 10]),
    ]);

    $this->service->checkSubMemberAchievements($sub);

    $creator->refresh();
    expect($creator->achievements()->count())->toBe(0);
});

test('checkSubPostsAchievements unlocks post achievements', function (): void {
    $creator = User::factory()->create();

    $sub = Sub::create([
        'name' => 'active-sub',
        'display_name' => 'Active Sub',
        'created_by' => $creator->id,
        'posts_count' => 50,
        'icon' => '📰',
        'color' => '#00FF00',
    ]);

    Achievement::create([
        'slug' => 'sub-posts-10',
        'name' => '10 Posts',
        'description' => 'Reach 10 posts',
        'icon' => '📝',
        'type' => 'post',
        'karma_bonus' => 10,
        'requirements' => json_encode(['posts' => 10]),
    ]);

    Achievement::create([
        'slug' => 'sub-posts-50',
        'name' => '50 Posts',
        'description' => 'Reach 50 posts',
        'icon' => '📝',
        'type' => 'post',
        'karma_bonus' => 25,
        'requirements' => json_encode(['posts' => 50]),
    ]);

    $this->service->checkSubPostsAchievements($sub);

    $creator->refresh();
    expect($creator->achievements()->count())->toBe(2);
});

test('checkSubAchievements checks all sub achievements', function (): void {
    $creator = User::factory()->create();

    $sub = Sub::create([
        'name' => 'complete-sub',
        'display_name' => 'Complete Sub',
        'created_by' => $creator->id,
        'members_count' => 100,
        'posts_count' => 50,
        'icon' => '🏆',
        'color' => '#FFD700',
    ]);

    // Create member achievements
    Achievement::create([
        'slug' => 'sub-members-100',
        'name' => '100 Members',
        'description' => 'Reach 100 members',
        'icon' => '👥',
        'type' => 'special',
        'karma_bonus' => 50,
        'requirements' => json_encode(['members' => 100]),
    ]);

    // Create post achievements
    Achievement::create([
        'slug' => 'sub-posts-50',
        'name' => '50 Posts',
        'description' => 'Reach 50 posts',
        'icon' => '📝',
        'type' => 'post',
        'karma_bonus' => 25,
        'requirements' => json_encode(['posts' => 50]),
    ]);

    $this->service->checkSubAchievements($sub);

    $creator->refresh();
    expect($creator->achievements()->count())->toBeGreaterThanOrEqual(2);
});

test('checkSubMemberAchievements handles sub without creator', function (): void {
    // Create a sub with a creator that will be deleted
    $deletedCreator = User::factory()->create();
    $creatorId = $deletedCreator->id;

    $sub = Sub::create([
        'name' => 'orphan-sub',
        'display_name' => 'Orphan Sub',
        'created_by' => $creatorId,
        'members_count' => 100,
        'icon' => '❓',
        'color' => '#CCCCCC',
    ]);

    // Delete the creator
    $deletedCreator->delete();

    // Should not throw exception
    $this->service->checkSubMemberAchievements($sub);

    expect(true)->toBeTrue();
});
