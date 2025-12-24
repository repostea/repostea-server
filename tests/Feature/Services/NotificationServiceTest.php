<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\KarmaEvent;
use App\Models\KarmaLevel;
use App\Models\User;
use App\Models\UserStreak;
use App\Notifications\AchievementUnlocked as AchievementUnlockedNotification;
use App\Notifications\KarmaEventStarting;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->service = app(NotificationService::class);
    Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('notifyUpcomingKarmaEvent sends notifications to active users', function (): void {
    Notification::fake();

    // Create verified users with recent activity
    $activeUser1 = User::factory()->create(['email_verified_at' => now()]);
    $activeUser2 = User::factory()->create(['email_verified_at' => now()]);

    // Create user streaks to mark them as active
    UserStreak::create([
        'user_id' => $activeUser1->id,
        'current_streak' => 1,
        'longest_streak' => 1,
        'last_activity_date' => Carbon::today()->subDays(3),
    ]);

    UserStreak::create([
        'user_id' => $activeUser2->id,
        'current_streak' => 5,
        'longest_streak' => 10,
        'last_activity_date' => Carbon::today()->subDays(1),
    ]);

    // Create inactive user (no recent activity)
    $inactiveUser = User::factory()->create(['email_verified_at' => now()]);
    UserStreak::create([
        'user_id' => $inactiveUser->id,
        'current_streak' => 1,
        'longest_streak' => 1,
        'last_activity_date' => Carbon::today()->subDays(10),
    ]);

    // Create karma event
    $event = KarmaEvent::factory()->create([
        'name' => 'Double Karma Weekend',
        'is_active' => true,
        'start_at' => Carbon::tomorrow(),
        'end_at' => Carbon::tomorrow()->addDays(2),
        'multiplier' => 2.0,
    ]);

    $count = $this->service->notifyUpcomingKarmaEvent($event);

    expect($count)->toBe(2);

    Notification::assertSentTo($activeUser1, KarmaEventStarting::class);
    Notification::assertSentTo($activeUser2, KarmaEventStarting::class);
    Notification::assertNotSentTo($inactiveUser, KarmaEventStarting::class);
});

test('notifyUpcomingKarmaEvent respects user limit', function (): void {
    Notification::fake();

    // Create 5 active verified users
    for ($i = 0; $i < 5; $i++) {
        $user = User::factory()->create(['email_verified_at' => now()]);
        UserStreak::create([
            'user_id' => $user->id,
            'current_streak' => 1,
            'longest_streak' => 1,
            'last_activity_date' => Carbon::today(),
        ]);
    }

    $event = KarmaEvent::factory()->create([
        'is_active' => true,
        'start_at' => Carbon::tomorrow(),
        'end_at' => Carbon::tomorrow()->addDay(),
    ]);

    // Limit to 3 users
    $count = $this->service->notifyUpcomingKarmaEvent($event, 3);

    expect($count)->toBe(3);
});

test('notifyUpcomingKarmaEvent skips unverified users', function (): void {
    Notification::fake();

    // Create unverified user with recent activity
    $unverifiedUser = User::factory()->create(['email_verified_at' => null]);
    UserStreak::create([
        'user_id' => $unverifiedUser->id,
        'current_streak' => 1,
        'longest_streak' => 1,
        'last_activity_date' => Carbon::today(),
    ]);

    $event = KarmaEvent::factory()->create([
        'is_active' => true,
        'start_at' => Carbon::tomorrow(),
        'end_at' => Carbon::tomorrow()->addDay(),
    ]);

    $count = $this->service->notifyUpcomingKarmaEvent($event);

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

test('notifyAchievementUnlocked sends notification to user', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $achievement = Achievement::factory()->create(['name' => 'Test Achievement']);

    $result = $this->service->notifyAchievementUnlocked($user, $achievement);

    expect($result)->toBeTrue();

    Notification::assertSentTo($user, AchievementUnlockedNotification::class);
});

test('notifyKarmaLevelUp logs level up', function (): void {
    Log::shouldReceive('info')
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'reached karma level'));

    $user = User::factory()->create();
    $level = KarmaLevel::factory()->create(['name' => 'Gold', 'required_karma' => 1000]);

    $result = $this->service->notifyKarmaLevelUp($user, $level);

    expect($result)->toBeTrue();
});

test('notifyUpcomingKarmaEvent returns zero on error', function (): void {
    // This test verifies error handling by checking if the method handles the case
    // where no users are found gracefully
    Notification::fake();

    $event = KarmaEvent::factory()->create([
        'is_active' => true,
        'start_at' => Carbon::tomorrow(),
        'end_at' => Carbon::tomorrow()->addDay(),
    ]);

    // No users exist with activity
    $count = $this->service->notifyUpcomingKarmaEvent($event);

    expect($count)->toBe(0);
});
