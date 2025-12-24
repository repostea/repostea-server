<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserPreference;
use App\Services\WebPushService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->service = app(WebPushService::class);
    Carbon::setTestNow(Carbon::parse('2025-01-15 14:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('isSnoozed returns false when user has no preferences', function (): void {
    $user = User::factory()->create();

    expect($this->service->isSnoozed($user))->toBeFalse();
});

test('isSnoozed returns false when not snoozed', function (): void {
    $user = User::factory()->create();
    UserPreference::create([
        'user_id' => $user->id,
        'snoozed_until' => null,
    ]);

    expect($this->service->isSnoozed($user))->toBeFalse();
});

test('isSnoozed returns true when snoozed until future', function (): void {
    $user = User::factory()->create();
    UserPreference::create([
        'user_id' => $user->id,
        'snoozed_until' => Carbon::now()->addHours(2),
    ]);

    expect($this->service->isSnoozed($user))->toBeTrue();
});

test('isSnoozed returns false when snooze has expired', function (): void {
    $user = User::factory()->create();
    UserPreference::create([
        'user_id' => $user->id,
        'snoozed_until' => Carbon::now()->subHours(1),
    ]);

    expect($this->service->isSnoozed($user))->toBeFalse();
});

test('isWithinQuietHours returns false when quiet hours disabled', function (): void {
    $user = User::factory()->create();
    UserPreference::create([
        'user_id' => $user->id,
        'quiet_hours_enabled' => false,
    ]);

    expect($this->service->isWithinQuietHours($user))->toBeFalse();
});

test('isWithinQuietHours returns true when within quiet hours', function (): void {
    // Current time is 14:00, set quiet hours 13:00-15:00
    $user = User::factory()->create();
    UserPreference::create([
        'user_id' => $user->id,
        'quiet_hours_enabled' => true,
        'quiet_hours_start' => '13:00',
        'quiet_hours_end' => '15:00',
        'timezone' => 'UTC',
    ]);

    expect($this->service->isWithinQuietHours($user))->toBeTrue();
});

test('isWithinQuietHours returns false when outside quiet hours', function (): void {
    // Current time is 14:00, set quiet hours 22:00-08:00
    $user = User::factory()->create();
    UserPreference::create([
        'user_id' => $user->id,
        'quiet_hours_enabled' => true,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '08:00',
        'timezone' => 'UTC',
    ]);

    expect($this->service->isWithinQuietHours($user))->toBeFalse();
});

test('shouldSendInstant returns false when snoozed', function (): void {
    $user = User::factory()->create();
    UserPreference::create([
        'user_id' => $user->id,
        'snoozed_until' => Carbon::now()->addHours(2),
        'notification_preferences' => ['push' => ['enabled' => true]],
    ]);

    expect($this->service->shouldSendInstant($user, 'comments'))->toBeFalse();
});

test('shouldSendInstant returns false when within quiet hours', function (): void {
    $user = User::factory()->create();
    UserPreference::create([
        'user_id' => $user->id,
        'quiet_hours_enabled' => true,
        'quiet_hours_start' => '13:00',
        'quiet_hours_end' => '15:00',
        'timezone' => 'UTC',
        'notification_preferences' => ['push' => ['enabled' => true]],
    ]);

    expect($this->service->shouldSendInstant($user, 'comments'))->toBeFalse();
});

test('snoozeNotifications sets snooze time', function (): void {
    $user = User::factory()->create();
    $prefs = UserPreference::create([
        'user_id' => $user->id,
    ]);

    $this->service->snoozeNotifications($user, 3);

    $prefs->refresh();
    expect($prefs->snoozed_until)->not->toBeNull();
    expect($prefs->snoozed_until->diffInHours(Carbon::now()))->toBeLessThanOrEqual(3);
});

test('snoozeUntilTomorrow sets snooze until tomorrow morning', function (): void {
    $user = User::factory()->create();
    $prefs = UserPreference::create([
        'user_id' => $user->id,
        'timezone' => 'UTC',
    ]);

    $this->service->snoozeUntilTomorrow($user, 9);

    $prefs->refresh();
    expect($prefs->snoozed_until)->not->toBeNull();
    expect($prefs->snoozed_until->isAfter(Carbon::now()))->toBeTrue();
});

test('unsnooze clears snooze time', function (): void {
    $user = User::factory()->create();
    $prefs = UserPreference::create([
        'user_id' => $user->id,
        'snoozed_until' => Carbon::now()->addHours(5),
    ]);

    $this->service->unsnooze($user);

    $prefs->refresh();
    expect($prefs->snoozed_until)->toBeNull();
});

test('sendToUser returns false when preferences block notification', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    UserPreference::create([
        'user_id' => $user->id,
        'snoozed_until' => Carbon::now()->addHours(2),
    ]);

    $result = $this->service->sendToUser($user, [
        'title' => 'Test',
        'body' => 'Test body',
    ], 'comments');

    expect($result)->toBeFalse();
    Notification::assertNothingSent();
});

test('sendToUser returns false when user has no subscriptions', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    UserPreference::create([
        'user_id' => $user->id,
        'notification_preferences' => [
            'push' => [
                'enabled' => true,
                'categories' => ['comments' => true],
            ],
        ],
    ]);

    $result = $this->service->sendToUser($user, [
        'title' => 'Test',
        'body' => 'Test body',
    ], 'comments');

    expect($result)->toBeFalse();
});

test('snooze methods handle missing preferences gracefully', function (): void {
    $user = User::factory()->create();

    // These should not throw
    $this->service->snoozeNotifications($user, 3);
    $this->service->snoozeUntilTomorrow($user, 9);
    $this->service->unsnooze($user);

    expect(true)->toBeTrue();
});
