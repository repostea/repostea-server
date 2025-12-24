<?php

declare(strict_types=1);

use App\Jobs\SendQuietHoursSummary;
use App\Models\ActivityPubDeliveryLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Promote pending posts to frontpage when there is space
Schedule::command('posts:promote-pending')->everyFiveMinutes();

// Karma statistics and rankings scheduler
Schedule::command('karma:update-today')->everyFiveMinutes();
Schedule::command('rankings:calculate-karma --timeframe=all')->everyTenMinutes();
Schedule::command('rankings:calculate-karma --timeframe=month')->everyTenMinutes();
Schedule::command('rankings:calculate-karma --timeframe=week')->everyTenMinutes();
Schedule::command('rankings:calculate-karma --timeframe=today')->everyTenMinutes();

// Cleanup unverified users daily at 6:00 AM
// Only processes users without activity (soft-delete)
Schedule::command('users:cleanup-unverified --force')->dailyAt('06:00');

// Award weekly seals to users based on karma level
// Runs every Monday at 00:00 (midnight)
Schedule::command('seals:award-weekly')->weekly()->mondays()->at('00:00');

// Delete expired Agora messages every 30 minutes
Schedule::command('agora:delete-expired')->everyThirtyMinutes();

// Prune inactive authentication tokens daily at 5:00 AM
Schedule::command('tokens:prune --days=30')->dailyAt('05:00');

// Send quiet hours summary push notifications hourly
// This checks for users whose quiet hours ended and sends them a summary
Schedule::job(new SendQuietHoursSummary())->hourly();

// Clean up old ActivityPub delivery logs daily at 4:00 AM
// Keeps logs for 30 days for debugging purposes
Artisan::command('activitypub:cleanup-logs {--days=30 : Number of days to keep}', function (int $days = 30): void {
    $deleted = ActivityPubDeliveryLog::cleanup($days);
    $this->info("Deleted {$deleted} old ActivityPub delivery logs (older than {$days} days).");
})->purpose('Clean up old ActivityPub delivery logs');

Schedule::command('activitypub:cleanup-logs --days=30')->dailyAt('04:00');
