<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserStreak;
use App\Services\StreakService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->service = app(StreakService::class);
    $this->user = User::factory()->create();
    Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('first activity creates streak with 1', function (): void {
    $streak = $this->service->recordActivity($this->user);

    expect($streak->current_streak)->toBe(1);
    expect($streak->longest_streak)->toBe(1);
    expect($streak->last_activity_date->toDateString())->toBe('2025-01-15');
});

test('same day activity does not increment streak', function (): void {
    // First activity
    $this->service->recordActivity($this->user);

    // Second activity on same day
    $streak = $this->service->recordActivity($this->user);

    expect($streak->current_streak)->toBe(1);
});

test('consecutive day activity increments streak', function (): void {
    // Activity on Jan 14
    Carbon::setTestNow(Carbon::parse('2025-01-14 12:00:00'));
    $this->service->recordActivity($this->user);

    // Activity on Jan 15
    Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));
    $streak = $this->service->recordActivity($this->user);

    expect($streak->current_streak)->toBe(2);
    expect($streak->longest_streak)->toBe(2);
});

test('gap of more than one day resets streak', function (): void {
    // Activity on Jan 12
    Carbon::setTestNow(Carbon::parse('2025-01-12 12:00:00'));
    $this->service->recordActivity($this->user);

    // Activity on Jan 15 (2 day gap)
    Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));
    $streak = $this->service->recordActivity($this->user);

    expect($streak->current_streak)->toBe(1);
});

test('weekend gap Friday to Monday continues streak', function (): void {
    // Activity on Friday Jan 17
    Carbon::setTestNow(Carbon::parse('2025-01-17 12:00:00'));
    $this->service->recordActivity($this->user);

    // Activity on Monday Jan 20
    Carbon::setTestNow(Carbon::parse('2025-01-20 12:00:00'));
    $streak = $this->service->recordActivity($this->user);

    expect($streak->current_streak)->toBe(2);
});

test('updates longest streak when current exceeds it', function (): void {
    // Build up a streak of 3 days
    Carbon::setTestNow(Carbon::parse('2025-01-13 12:00:00'));
    $this->service->recordActivity($this->user);

    Carbon::setTestNow(Carbon::parse('2025-01-14 12:00:00'));
    $this->service->recordActivity($this->user);

    Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));
    $streak = $this->service->recordActivity($this->user);

    expect($streak->current_streak)->toBe(3);
    expect($streak->longest_streak)->toBe(3);

    // Break the streak with a 2-day gap
    Carbon::setTestNow(Carbon::parse('2025-01-18 12:00:00'));
    $this->service->recordActivity($this->user);

    // Continue for another day
    Carbon::setTestNow(Carbon::parse('2025-01-19 12:00:00'));
    $streak = $this->service->recordActivity($this->user);

    // Current is 2, but longest should remain 3
    expect($streak->current_streak)->toBe(2);
    expect($streak->longest_streak)->toBe(3);
});

test('streak achievements are checked at milestones', function (): void {
    Event::fake();

    // Create streak achievement
    Achievement::factory()->create(['slug' => 'streak-7']);

    // Create existing streak of 6 days with last activity yesterday
    UserStreak::create([
        'user_id' => $this->user->id,
        'current_streak' => 6,
        'longest_streak' => 6,
        'last_activity_date' => Carbon::parse('2025-01-14'),
    ]);

    // Activity today should make it 7
    $streak = $this->service->recordActivity($this->user);

    expect($streak->current_streak)->toBe(7);
    expect($this->user->achievements()->where('slug', 'streak-7')->exists())->toBeTrue();
});

test('preserves existing streak record', function (): void {
    UserStreak::create([
        'user_id' => $this->user->id,
        'current_streak' => 5,
        'longest_streak' => 10,
        'last_activity_date' => Carbon::parse('2025-01-14'),
    ]);

    $streak = $this->service->recordActivity($this->user);

    expect($streak->current_streak)->toBe(6);
    expect($streak->longest_streak)->toBe(10);
});

test('karma multiplier calculated correctly', function (): void {
    $streak = UserStreak::create([
        'user_id' => $this->user->id,
        'current_streak' => 0,
        'longest_streak' => 0,
        'last_activity_date' => null,
    ]);

    // No streak = 1.0
    expect($streak->karma_multiplier)->toBe(1.0);

    // 7 days = 1.2
    $streak->update(['current_streak' => 7]);
    $streak->refresh();
    expect($streak->karma_multiplier)->toBe(1.2);

    // 30 days = 1.5
    $streak->update(['current_streak' => 30]);
    $streak->refresh();
    expect($streak->karma_multiplier)->toBe(1.5);

    // 90 days = 2.0
    $streak->update(['current_streak' => 90]);
    $streak->refresh();
    expect($streak->karma_multiplier)->toBe(2.0);

    // 365 days = 3.0
    $streak->update(['current_streak' => 365]);
    $streak->refresh();
    expect($streak->karma_multiplier)->toBe(3.0);
});
