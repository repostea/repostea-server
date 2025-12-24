<?php

declare(strict_types=1);

use App\Events\AchievementUnlocked;
use App\Models\Achievement;
use App\Models\Sub;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->service = app(AchievementService::class);
    $this->user = User::factory()->create(['karma_points' => 100]);
});

test('unlockIfExists unlocks existing achievement', function (): void {
    Event::fake();
    $achievement = Achievement::factory()->create(['slug' => 'test-achievement']);

    $result = $this->service->unlockIfExists($this->user, 'test-achievement');

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($achievement->id);
    expect($this->user->achievements()->where('achievement_id', $achievement->id)->exists())->toBeTrue();

    Event::assertDispatched(AchievementUnlocked::class);
});

test('unlockIfExists returns null for non-existent achievement', function (): void {
    $result = $this->service->unlockIfExists($this->user, 'non-existent-slug');

    expect($result)->toBeNull();
});

test('unlock attaches achievement to user', function (): void {
    Event::fake();
    $achievement = Achievement::factory()->create(['karma_bonus' => 50]);

    $result = $this->service->unlock($this->user, $achievement);

    expect($result->id)->toBe($achievement->id);

    $pivot = $this->user->achievements()
        ->where('achievement_id', $achievement->id)
        ->first()
        ->pivot;

    expect($pivot->progress)->toBe(100);
    expect($pivot->unlocked_at)->not->toBeNull();
});

test('unlock dispatches AchievementUnlocked event', function (): void {
    Event::fake();
    $achievement = Achievement::factory()->create();

    $this->service->unlock($this->user, $achievement);

    Event::assertDispatched(AchievementUnlocked::class, fn ($event) => $event->user->id === $this->user->id
            && $event->achievement->id === $achievement->id);
});

test('unlock gives karma bonus for new achievements', function (): void {
    Event::fake();
    $initialKarma = $this->user->karma_points;
    $achievement = Achievement::factory()->create(['karma_bonus' => 50]);

    $this->service->unlock($this->user, $achievement);

    $this->user->refresh();
    expect($this->user->karma_points)->toBe($initialKarma + 50);
});

test('unlock does not give karma bonus if already unlocked', function (): void {
    Event::fake();
    $achievement = Achievement::factory()->create(['karma_bonus' => 50]);

    // First unlock
    $this->service->unlock($this->user, $achievement);
    $this->user->refresh();
    $karmaAfterFirst = $this->user->karma_points;

    // Second unlock (should not add karma again)
    $this->service->unlock($this->user, $achievement);
    $this->user->refresh();

    expect($this->user->karma_points)->toBe($karmaAfterFirst);
});

test('unlock does not dispatch event if already unlocked', function (): void {
    Event::fake();
    $achievement = Achievement::factory()->create();

    // First unlock
    $this->service->unlock($this->user, $achievement);
    Event::assertDispatchedTimes(AchievementUnlocked::class, 1);

    // Second unlock (should not dispatch again)
    $this->service->unlock($this->user, $achievement);
    Event::assertDispatchedTimes(AchievementUnlocked::class, 1);
});

test('updateProgress creates progress entry', function (): void {
    $achievement = Achievement::factory()->create(['slug' => 'progress-test']);

    $result = $this->service->updateProgress($this->user, 'progress-test', 30);

    expect($result)->not->toBeNull();

    $pivot = $this->user->achievements()
        ->where('achievement_id', $achievement->id)
        ->first()
        ->pivot;

    expect($pivot->progress)->toBe(30);
    expect($pivot->unlocked_at)->toBeNull();
});

test('updateProgress adds to existing progress', function (): void {
    $achievement = Achievement::factory()->create(['slug' => 'progress-add']);

    $this->service->updateProgress($this->user, 'progress-add', 30);
    $this->service->updateProgress($this->user, 'progress-add', 40);

    $pivot = $this->user->achievements()
        ->where('achievement_id', $achievement->id)
        ->first()
        ->pivot;

    expect($pivot->progress)->toBe(70);
});

test('updateProgress unlocks when reaching 100 percent', function (): void {
    Event::fake();
    $achievement = Achievement::factory()->create(['slug' => 'progress-unlock', 'karma_bonus' => 25]);
    $initialKarma = $this->user->karma_points;

    $this->service->updateProgress($this->user, 'progress-unlock', 100);

    $pivot = $this->user->achievements()
        ->where('achievement_id', $achievement->id)
        ->first()
        ->pivot;

    expect($pivot->progress)->toBe(100);
    expect($pivot->unlocked_at)->not->toBeNull();

    $this->user->refresh();
    expect($this->user->karma_points)->toBe($initialKarma + 25);

    Event::assertDispatched(AchievementUnlocked::class);
});

test('updateProgress caps at 100 percent', function (): void {
    Event::fake();
    $achievement = Achievement::factory()->create(['slug' => 'progress-cap']);

    $this->service->updateProgress($this->user, 'progress-cap', 150);

    $pivot = $this->user->achievements()
        ->where('achievement_id', $achievement->id)
        ->first()
        ->pivot;

    expect($pivot->progress)->toBe(100);
});

test('updateProgress returns null for non-existent achievement', function (): void {
    $result = $this->service->updateProgress($this->user, 'non-existent', 50);

    expect($result)->toBeNull();
});

test('checkSubMemberAchievements unlocks at thresholds', function (): void {
    Event::fake();

    // Create achievements for each threshold
    Achievement::factory()->create(['slug' => 'sub-members-10']);
    Achievement::factory()->create(['slug' => 'sub-members-50']);
    Achievement::factory()->create(['slug' => 'sub-members-100']);

    $creator = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $creator->id,
        'members_count' => 55,
        'icon' => '📝',
        'color' => '#6366F1',
    ]);

    $this->service->checkSubMemberAchievements($sub);

    // Should have unlocked 10 and 50 achievements
    expect($creator->achievements()->where('slug', 'sub-members-10')->exists())->toBeTrue();
    expect($creator->achievements()->where('slug', 'sub-members-50')->exists())->toBeTrue();
    expect($creator->achievements()->where('slug', 'sub-members-100')->exists())->toBeFalse();
});

test('checkSubPostsAchievements unlocks at thresholds', function (): void {
    Event::fake();

    Achievement::factory()->create(['slug' => 'sub-posts-10']);
    Achievement::factory()->create(['slug' => 'sub-posts-50']);
    Achievement::factory()->create(['slug' => 'sub-posts-100']);

    $creator = User::factory()->create();
    $sub = Sub::create([
        'name' => 'posts-test-sub',
        'display_name' => 'Posts Test Sub',
        'created_by' => $creator->id,
        'members_count' => 1,
        'posts_count' => 75,
        'icon' => '📝',
        'color' => '#6366F1',
    ]);

    $this->service->checkSubPostsAchievements($sub);

    expect($creator->achievements()->where('slug', 'sub-posts-10')->exists())->toBeTrue();
    expect($creator->achievements()->where('slug', 'sub-posts-50')->exists())->toBeTrue();
    expect($creator->achievements()->where('slug', 'sub-posts-100')->exists())->toBeFalse();
});

test('checkSubMemberAchievements does nothing if no creator', function (): void {
    Event::fake();

    $sub = Sub::create([
        'name' => 'orphan-sub',
        'display_name' => 'Orphan Sub',
        'created_by' => null,
        'members_count' => 100,
        'icon' => '📝',
        'color' => '#6366F1',
    ]);

    // Should not throw
    $this->service->checkSubMemberAchievements($sub);

    Event::assertNotDispatched(AchievementUnlocked::class);
});

test('checkSubAchievements calls both member and posts checks', function (): void {
    Event::fake();

    Achievement::factory()->create(['slug' => 'sub-members-10']);
    Achievement::factory()->create(['slug' => 'sub-posts-10']);

    $creator = User::factory()->create();
    $sub = Sub::create([
        'name' => 'combo-sub',
        'display_name' => 'Combo Sub',
        'created_by' => $creator->id,
        'members_count' => 15,
        'posts_count' => 12,
        'icon' => '📝',
        'color' => '#6366F1',
    ]);

    $this->service->checkSubAchievements($sub);

    expect($creator->achievements()->where('slug', 'sub-members-10')->exists())->toBeTrue();
    expect($creator->achievements()->where('slug', 'sub-posts-10')->exists())->toBeTrue();
});
