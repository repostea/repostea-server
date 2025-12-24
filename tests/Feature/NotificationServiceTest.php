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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->service = new NotificationService();
});

test('notifyUpcomingKarmaEvent notifies active users', function (): void {
    Notification::fake();

    // Create active users with streaks
    $activeUser1 = User::factory()->create(['email_verified_at' => now()]);
    UserStreak::create([
        'user_id' => $activeUser1->id,
        'current_streak' => 5,
        'longest_streak' => 10,
        'last_activity_date' => now()->subDays(2),
    ]);

    $activeUser2 = User::factory()->create(['email_verified_at' => now()]);
    UserStreak::create([
        'user_id' => $activeUser2->id,
        'current_streak' => 3,
        'longest_streak' => 5,
        'last_activity_date' => now()->subDays(1),
    ]);

    // Create inactive user (should not be notified)
    $inactiveUser = User::factory()->create(['email_verified_at' => now()]);
    UserStreak::create([
        'user_id' => $inactiveUser->id,
        'current_streak' => 0,
        'longest_streak' => 1,
        'last_activity_date' => now()->subDays(30),
    ]);

    $event = KarmaEvent::create([
        'name' => 'Test Event',
        'description' => 'Test description',
        'multiplier' => 2.0,
        'starts_at' => now()->addDays(1),
        'ends_at' => now()->addDays(2),
        'is_active' => false,
    ]);

    $count = $this->service->notifyUpcomingKarmaEvent($event);

    expect($count)->toBe(2);
    Notification::assertSentTo([$activeUser1, $activeUser2], KarmaEventStarting::class);
    Notification::assertNotSentTo($inactiveUser, KarmaEventStarting::class);
});

test('notifyUpcomingKarmaEvent respeta user limit', function (): void {
    Notification::fake();

    // Create 5 active users
    $users = [];
    for ($i = 0; $i < 5; $i++) {
        $user = User::factory()->create(['email_verified_at' => now()]);
        UserStreak::create([
            'user_id' => $user->id,
            'current_streak' => 1,
            'longest_streak' => 1,
            'last_activity_date' => now(),
        ]);
        $users[] = $user;
    }

    $event = KarmaEvent::create([
        'name' => 'Test Event',
        'description' => 'Test description',
        'multiplier' => 2.0,
        'starts_at' => now()->addDays(1),
        'ends_at' => now()->addDays(2),
        'is_active' => false,
    ]);

    $count = $this->service->notifyUpcomingKarmaEvent($event, 3);

    expect($count)->toBe(3);

    // Verify that exactly 3 users were notified
    $notifiedCount = 0;
    foreach ($users as $user) {
        $notifications = Notification::sent($user, KarmaEventStarting::class);
        if ($notifications->count() > 0) {
            $notifiedCount++;
        }
    }
    expect($notifiedCount)->toBe(3);
});

test('notifyUpcomingKarmaEvent excludes users without verified email', function (): void {
    Notification::fake();

    // Create user without verified email
    $unverifiedUser = User::factory()->create(['email_verified_at' => null]);
    UserStreak::create([
        'user_id' => $unverifiedUser->id,
        'current_streak' => 5,
        'longest_streak' => 10,
        'last_activity_date' => now(),
    ]);

    // Create user with verified email
    $verifiedUser = User::factory()->create(['email_verified_at' => now()]);
    UserStreak::create([
        'user_id' => $verifiedUser->id,
        'current_streak' => 5,
        'longest_streak' => 10,
        'last_activity_date' => now(),
    ]);

    $event = KarmaEvent::create([
        'name' => 'Test Event',
        'description' => 'Test description',
        'multiplier' => 2.0,
        'starts_at' => now()->addDays(1),
        'ends_at' => now()->addDays(2),
        'is_active' => false,
    ]);

    $count = $this->service->notifyUpcomingKarmaEvent($event);

    expect($count)->toBe(1);
    Notification::assertSentTo($verifiedUser, KarmaEventStarting::class);
    Notification::assertNotSentTo($unverifiedUser, KarmaEventStarting::class);
});

test('notifyAchievementUnlocked sends notification to user', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $achievement = Achievement::create([
        'slug' => 'test-achievement',
        'name' => 'Test Achievement',
        'description' => 'Test description',
        'icon' => '🏆',
        'type' => 'post',
        'karma_bonus' => 100,
        'requirements' => json_encode(['posts_count' => 1]),
    ]);

    $result = $this->service->notifyAchievementUnlocked($user, $achievement);

    expect($result)->toBeTrue();
    Notification::assertSentTo($user, AchievementUnlockedNotification::class);
});

test('notifyKarmaLevelUp registra log correctamente', function (): void {
    Log::spy();

    $user = User::factory()->create();
    $level = KarmaLevel::create([
        'name' => 'Master',
        'required_karma' => 5000,
    ]);

    $result = $this->service->notifyKarmaLevelUp($user, $level);

    expect($result)->toBeTrue();
    Log::shouldHaveReceived('info')
        ->once()
        ->with("User {$user->id} reached karma level: Master");
});

test('notifyUpcomingKarmaEvent only notifies users active in last 7 days', function (): void {
    Notification::fake();

    // User active 6 days ago (should be notified)
    $recentUser = User::factory()->create(['email_verified_at' => now()]);
    UserStreak::create([
        'user_id' => $recentUser->id,
        'current_streak' => 6,
        'longest_streak' => 6,
        'last_activity_date' => now()->subDays(6),
    ]);

    // User active 8 days ago (should NOT be notified)
    $oldUser = User::factory()->create(['email_verified_at' => now()]);
    UserStreak::create([
        'user_id' => $oldUser->id,
        'current_streak' => 0,
        'longest_streak' => 10,
        'last_activity_date' => now()->subDays(8),
    ]);

    $event = KarmaEvent::create([
        'name' => 'Test Event',
        'description' => 'Test description',
        'multiplier' => 2.0,
        'starts_at' => now()->addDays(1),
        'ends_at' => now()->addDays(2),
        'is_active' => false,
    ]);

    $count = $this->service->notifyUpcomingKarmaEvent($event);

    expect($count)->toBe(1);
    Notification::assertSentTo($recentUser, KarmaEventStarting::class);
    Notification::assertNotSentTo($oldUser, KarmaEventStarting::class);
});
