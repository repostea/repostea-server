<?php

declare(strict_types=1);

use App\Http\Middleware\ActionRateLimiter;
use App\Http\Middleware\ActivityPubRateLimiter;
use App\Http\Middleware\ApiLocalization;
use App\Http\Middleware\CheckUserBanned;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\IpBlockMiddleware;
use App\Http\Middleware\LocaleAwareAuthentication;
use App\Http\Middleware\OptionalAuthentication;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackUserActivity;
use App\Http\Middleware\UserStatusMiddleware;
use App\Http\Middleware\ViewRateLimiter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Plugins\PluginServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__ . '/../routes/channels.php')
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->alias([
            'setlocale' => SetLocale::class,
            'track.activity' => TrackUserActivity::class,
            'locale.auth' => LocaleAwareAuthentication::class,
            'auth.optional' => OptionalAuthentication::class,
            'view.rate.limit' => ViewRateLimiter::class,
            'check.banned' => CheckUserBanned::class,
            'action.rate.limit' => ActionRateLimiter::class,
            'check.status' => UserStatusMiddleware::class,
            'admin' => EnsureUserIsAdmin::class,
            'ip.block' => IpBlockMiddleware::class,
            'verified' => EnsureEmailIsVerified::class,
            'activitypub.rate.limit' => ActivityPubRateLimiter::class,
        ]);

        $middleware->api(append: [
            IpBlockMiddleware::class,
            TrackUserActivity::class,
            OptionalAuthentication::class,
            UserStatusMiddleware::class,
            ApiLocalization::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'activitypub/inbox',
            'activitypub/users/*/inbox',
            'activitypub/groups/*/inbox',
            '__cypress__/*',
        ]);

        // Redirect unauthenticated users to admin login (only for web routes)
        $middleware->redirectGuestsTo(function ($request) {
            // For API requests, return null so Laravel returns 401 JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return;
            }

            return route('admin.login');
        });
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })
    ->withEvents(discover: [
        __DIR__ . '/../app/Listeners',
    ])
    ->withSchedule(static function (Schedule $schedule): void {
        // Mbin Import Sync - Run every minute to sync new content from last 24 hours
        // DISABLED: Uncomment to enable mbin import sync
        // $schedule->command('mbin:import --all --sync --hours=24 --no-interaction')
        //     ->everyMinute()
        //     ->withoutOverlapping()
        //     ->runInBackground();

        // Votes Recalculation - Run every 5 minutes to keep vote counts synchronized
        $schedule->command('votes:recalculate')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Twitter Auto-Post - Check every hour for posts ready to tweet
        $schedule->command('twitter:post-pending')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Rate Limit Logs Cleanup - Run daily at 3 AM
        $schedule->command('rate-limit:cleanup --force')
            ->dailyAt('03:00')
            ->withoutOverlapping();

        // IP Block Expiration - Run every hour to deactivate expired blocks
        $schedule->call(static function (): void {
            App\Models\IpBlock::deactivateExpired();
        })
            ->hourly()
            ->name('ip-blocks:deactivate-expired')
            ->withoutOverlapping();

        // Recalculate vote and comment counts - Run every 6 hours (only last 48 hours for performance)
        $schedule->command('posts:recalculate-counts --hours=48')
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground();

        // Calculate transparency statistics - Run every hour
        $schedule->command('transparency:calculate')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Calculate achievements for recently active users - Run every hour
        $schedule->command('achievements:calculate --recent=12')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Full achievement recalculation for all users - Run daily at 4 AM
        $schedule->command('achievements:calculate --all')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Sync user avatars from Mbin - Run every hour
        // DISABLED: Uncomment to enable avatar sync
        // $schedule->command('mbin:sync-avatars')
        //     ->hourly()
        //     ->withoutOverlapping()
        //     ->runInBackground();

        // Purge old deleted users - Run daily at 2 AM
        $schedule->command('users:purge-deleted')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        // Calculate spam scores for active users - Run every 15 minutes
        $schedule->command('spam:calculate-scores --hours=24 --min-activity=3')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Realtime post stats via WebSocket (dynamic throttling: 2-15s based on connections)
        $schedule->command('realtime:flush')
            ->everyFiveSeconds()
            ->runInBackground()
            ->appendOutputTo('/dev/null');
    })
    ->create();
