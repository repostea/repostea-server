<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityPubController;
use App\Http\Controllers\Admin\AbuseMonitoringController;
use App\Http\Controllers\Admin\ActivityPubController as AdminActivityPubController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminFederationController;
use App\Http\Controllers\Admin\AdminLegalReportController;
use App\Http\Controllers\Admin\AdminPostController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\IpBlockController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserApprovalController;
use App\Http\Controllers\AdminWebController;
use App\Http\Controllers\BlueskyWebAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ManifestoController;
use App\Http\Controllers\MultiActorActivityPubController;
use App\Http\Controllers\RssController;
use Illuminate\Support\Facades\Route;

// Dynamic robots.txt based on environment
Route::get('/robots.txt', static function () {
    $isStaging = config('app.env') === 'staging';

    $content = $isStaging
        ? "User-agent: *\nDisallow: /"
        : "User-agent: *\nDisallow:";

    return response($content, 200)
        ->header('Content-Type', 'text/plain');
});

Route::get('/', static fn () => redirect(app()->getLocale()));

Route::get('/language/{locale}', [LanguageController::class, 'changeLanguage'])->name('language.change');

// RSS Feeds - Grouped under /rss
Route::prefix('rss')->name('rss.')->group(static function (): void {
    Route::get('/published', [RssController::class, 'published'])->name('published');
    Route::get('/queued', [RssController::class, 'queued'])->name('queued');
});

// Bluesky OAuth Routes (web middleware for session-based DPoP/PKCE)
// The callback route MUST be named 'bluesky.oauth.redirect' - the revolution/laravel-bluesky
// package uses this name to generate redirect_uris in client-metadata.json
Route::get('/auth/bluesky/redirect', [BlueskyWebAuthController::class, 'redirect'])
    ->middleware('throttle:10,1');
Route::get('/auth/bluesky/callback', [BlueskyWebAuthController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('bluesky.oauth.redirect');

// ActivityPub Federation Endpoints
// Use multi-actor WebFinger (supports @user and !group)
Route::get('/.well-known/webfinger', [MultiActorActivityPubController::class, 'webfinger']);

Route::prefix('activitypub')->group(static function (): void {
    // Instance actor endpoints (backward compatible)
    Route::get('/actor', [ActivityPubController::class, 'actor']);
    Route::post('/inbox', [ActivityPubController::class, 'inbox'])->middleware('activitypub.rate.limit:300,1');
    Route::get('/outbox', [ActivityPubController::class, 'outbox']);
    Route::get('/followers', [ActivityPubController::class, 'followers']);
    Route::get('/posts/{post}', [ActivityPubController::class, 'post']);
    Route::get('/activities/{post}', [ActivityPubController::class, 'activity']);

    // Multi-actor endpoints (FEP-1b12)
    // User actors: @username@domain
    Route::get('/users/{username}', [MultiActorActivityPubController::class, 'userActor']);
    Route::post('/users/{username}/inbox', [MultiActorActivityPubController::class, 'userInbox'])->middleware('activitypub.rate.limit:300,1');
    Route::get('/users/{username}/outbox', [MultiActorActivityPubController::class, 'userOutbox']);
    Route::get('/users/{username}/followers', [MultiActorActivityPubController::class, 'userFollowers']);

    // Group actors: !groupname@domain
    Route::get('/groups/{name}', [MultiActorActivityPubController::class, 'groupActor']);
    Route::post('/groups/{name}/inbox', [MultiActorActivityPubController::class, 'groupInbox'])->middleware('activitypub.rate.limit:300,1');
    Route::get('/groups/{name}/outbox', [MultiActorActivityPubController::class, 'groupOutbox']);
    Route::get('/groups/{name}/followers', [MultiActorActivityPubController::class, 'groupFollowers']);

    // Notes (for dereferencing)
    Route::get('/notes/{post}', [MultiActorActivityPubController::class, 'note']);
});

Route::prefix('{locale}')
    ->where(['locale' => '[a-zA-Z]{2}'])
    ->middleware('setlocale')
    ->group(static function (): void {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        // Manifiesto
        Route::get('/manifesto', [ManifestoController::class, 'index'])->name('manifesto');
        Route::post('/manifesto/comment', [ManifestoController::class, 'comment'])->name('manifesto.comment');

        Route::view('/cookies', 'cookies')->name('cookies');
        Route::view('/privacy', 'privacy')->name('privacy');
        Route::view('/terms', 'terms')->name('terms');
        Route::view('/about', 'about')->name('about');
    });

Route::get('app/{location}/posts/{any}', static fn ($location = null, $any = null) => redirect("/app/{$location}/permalink/index.html?redirect=/posts/" . urlencode($any)))->where('any', '.*');

// Admin Panel Routes
Route::prefix('admin')->name('admin.')->group(static function (): void {
    // Auth routes (without middleware)
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    // Protected admin routes
    Route::middleware(['auth', 'can:access-admin'])->group(static function (): void {
        // Dashboard
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');

        // User Management
        Route::get('/users', [AdminUserController::class, 'index'])->name('users');

        // User Approval Management (must be before {user} route)
        Route::get('/users/pending', [UserApprovalController::class, 'index'])
            ->middleware('can:admin-only')
            ->name('users.pending');
        Route::get('/users/approval/stats', [UserApprovalController::class, 'stats'])
            ->middleware('can:admin-only')
            ->name('users.approval.stats');

        // Deleted Users Management (must be before {user} route)
        Route::get('/users/deleted', [AdminUserController::class, 'deleted'])
            ->middleware('can:admin-only')
            ->name('users.deleted');

        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/ban', [AdminUserController::class, 'ban'])->name('users.ban');
        Route::post('/users/{user}/unban', [AdminUserController::class, 'unban'])->name('users.unban');
        Route::post('/users/{user}/strike', [AdminUserController::class, 'giveStrike'])->name('users.strike');
        Route::delete('/strikes/{strike}', [AdminUserController::class, 'removeStrike'])->name('strikes.remove');
        Route::put('/bans/{ban}', [AdminUserController::class, 'editBan'])->name('bans.edit');
        Route::put('/strikes/{strike}', [AdminUserController::class, 'editStrike'])->name('strikes.edit');
        Route::post('/users/{user}/invitation-limit', [AdminUserController::class, 'updateInvitationLimit'])->name('users.invitation-limit');
        Route::post('/users/{user}/invitation-limit/reset', [AdminUserController::class, 'resetInvitationLimit'])->name('users.invitation-limit.reset');
        Route::post('/users/{user}/assign-role', [AdminUserController::class, 'assignRole'])->middleware('can:admin-only')->name('users.assign-role');
        Route::post('/users/{user}/remove-role', [AdminUserController::class, 'removeRole'])->middleware('can:admin-only')->name('users.remove-role');
        Route::post('/users/{user}/toggle-permission', [AdminUserController::class, 'togglePermission'])->middleware('can:admin-only')->name('users.toggle-permission');
        Route::post('/users/{user}/assign-achievement', [AdminUserController::class, 'assignAchievement'])->middleware('can:admin-only')->name('users.assign-achievement');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->middleware('can:admin-only')->name('users.destroy');
        Route::delete('/users/{user}/achievements/{achievement}', [AdminUserController::class, 'removeAchievement'])->middleware('can:admin-only')->name('users.remove-achievement');
        Route::post('/users/{id}/restore', [AdminUserController::class, 'restore'])->middleware('can:admin-only')->name('users.restore');
        Route::delete('/users/{id}/force-delete', [AdminUserController::class, 'forceDelete'])->middleware('can:admin-only')->name('users.force-delete');

        // Karma History
        Route::get('/karma-history', [AdminUserController::class, 'karmaHistory'])->name('karma-history');

        // Post Management
        Route::get('/posts', [AdminPostController::class, 'index'])->name('posts');
        Route::get('/posts/{post}', [AdminPostController::class, 'show'])->name('posts.view');
        Route::patch('/posts/{post}/moderation', [AdminPostController::class, 'updateModeration'])->name('posts.updateModeration');
        Route::post('/posts/{post}/hide', [AdminPostController::class, 'hide'])->name('posts.hide');
        Route::post('/posts/{post}/show', [AdminPostController::class, 'restore'])->name('posts.show');
        Route::post('/posts/{post}/approve', [AdminPostController::class, 'approve'])->name('posts.approve');
        Route::delete('/posts/{post}', [AdminPostController::class, 'destroy'])->name('posts.delete');
        Route::post('/posts/{post}/twitter', [AdminController::class, 'postToTwitter'])->name('posts.twitter');
        Route::post('/posts/{post}/twitter/repost', [AdminController::class, 'repostToTwitter'])->name('posts.twitter.repost');
        Route::post('/posts/{post}/federate', [AdminController::class, 'federatePost'])->name('posts.federate');

        // Comment Management
        Route::get('/comments', [AdminPostController::class, 'comments'])->name('comments');
        Route::post('/comments/{comment}/hide', [AdminPostController::class, 'hideComment'])->name('comments.hide');
        Route::post('/comments/{comment}/show', [AdminPostController::class, 'showComment'])->name('comments.show');
        Route::delete('/comments/{comment}', [AdminPostController::class, 'destroyComment'])->name('comments.delete');

        // Reports
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports');
        Route::get('/reports/{report}', [AdminReportController::class, 'show'])->name('reports.view');
        Route::post('/reports/{report}/resolve', [AdminReportController::class, 'resolve'])->name('reports.resolve');
        Route::post('/reports/{report}/dismiss', [AdminReportController::class, 'dismiss'])->name('reports.dismiss');
        Route::post('/reports/{report}/reopen', [AdminReportController::class, 'reopen'])->name('reports.reopen');
        Route::post('/reports/{report}/notes', [AdminReportController::class, 'addNote'])->name('reports.add-note');

        // Legal Reports (DMCA, abuse, etc.)
        Route::get('/legal-reports', [AdminLegalReportController::class, 'index'])->name('legal-reports');
        Route::get('/legal-reports/{legalReport}', [AdminLegalReportController::class, 'show'])->name('legal-reports.view');
        Route::post('/legal-reports/{legalReport}/status', [AdminLegalReportController::class, 'updateStatus'])->name('legal-reports.update-status');
        Route::post('/legal-reports/{legalReport}/notes', [AdminLegalReportController::class, 'addNote'])->name('legal-reports.add-note');
        Route::post('/legal-reports/{legalReport}/notify', [AdminLegalReportController::class, 'notify'])->name('legal-reports.notify');

        // Moderation Logs
        Route::get('/logs', [AdminController::class, 'moderationLogs'])->name('logs');

        // Scheduled Commands (admin only)
        Route::get('/scheduled-commands', [AdminController::class, 'scheduledCommands'])
            ->middleware('can:admin-only')
            ->name('scheduled-commands');
        Route::match(['get', 'post'], '/scheduled-commands/execute', [AdminController::class, 'executeCommand'])
            ->middleware('can:admin-only')
            ->name('scheduled-commands.execute');

        // System Status (admin only)
        Route::get('/system-status', [AdminWebController::class, 'systemStatus'])
            ->middleware('can:admin-only')
            ->name('system-status');

        // Error Logs (admin only)
        Route::get('/error-logs', [App\Http\Controllers\Admin\ErrorLogController::class, 'index'])
            ->middleware('can:admin-only')
            ->name('error-logs');
        Route::post('/error-logs/clear', [App\Http\Controllers\Admin\ErrorLogController::class, 'clear'])
            ->middleware('can:admin-only')
            ->name('error-logs.clear');
        Route::get('/error-logs/download', [App\Http\Controllers\Admin\ErrorLogController::class, 'download'])
            ->middleware('can:admin-only')
            ->name('error-logs.download');

        // Karma & Achievements Configuration (admins and moderators)
        Route::get('/karma-configuration', [AdminWebController::class, 'karmaConfiguration'])
            ->name('karma-configuration');

        // Abuse Monitoring
        Route::get('/abuse', [AbuseMonitoringController::class, 'index'])->name('abuse');
        Route::get('/abuse/user/{user}', [AbuseMonitoringController::class, 'userViolations'])->name('abuse.user');
        Route::get('/abuse/ip/{ip}', [AbuseMonitoringController::class, 'ipViolations'])->name('abuse.ip');
        Route::get('/abuse/realtime', [AbuseMonitoringController::class, 'realtimeStats'])->name('abuse.realtime');
        Route::get('/abuse/export', [AbuseMonitoringController::class, 'export'])->name('abuse.export');
        Route::post('/abuse/blacklist', [AbuseMonitoringController::class, 'blacklistIp'])->name('abuse.blacklist');

        // Spam Detection
        Route::get('/spam-detection', [AdminWebController::class, 'spamDetection'])->name('spam-detection');
        Route::get('/spam-logs', [AdminWebController::class, 'spamLogs'])->name('spam-logs');
        Route::post('/spam-detections/{id}/review', [AdminWebController::class, 'reviewSpamDetection'])->name('spam-detections.review');
        Route::get('/spam-configuration', [AdminWebController::class, 'spamConfiguration'])->name('spam-configuration');
        Route::post('/spam-configuration', [AdminWebController::class, 'updateSpamConfiguration'])->name('spam-configuration.update');

        // IP Blocking
        Route::get('/ip-blocks', [IpBlockController::class, 'index'])->name('ip-blocks.index');
        Route::get('/ip-blocks/create', [IpBlockController::class, 'create'])->name('ip-blocks.create');
        Route::post('/ip-blocks', [IpBlockController::class, 'store'])->name('ip-blocks.store');
        Route::get('/ip-blocks/{ipBlock}', [IpBlockController::class, 'show'])->name('ip-blocks.show');
        Route::get('/ip-blocks/{ipBlock}/edit', [IpBlockController::class, 'edit'])->name('ip-blocks.edit');
        Route::put('/ip-blocks/{ipBlock}', [IpBlockController::class, 'update'])->name('ip-blocks.update');
        Route::delete('/ip-blocks/{ipBlock}', [IpBlockController::class, 'destroy'])->name('ip-blocks.destroy');
        Route::post('/ip-blocks/bulk', [IpBlockController::class, 'bulkBlock'])->name('ip-blocks.bulk');
        Route::post('/ip-blocks/quick', [IpBlockController::class, 'quickBlock'])->name('ip-blocks.quick');
        Route::post('/abuse/remove-blacklist', [AbuseMonitoringController::class, 'removeIpBlacklist'])->name('abuse.remove-blacklist');
        Route::post('/abuse/cleanup', [AbuseMonitoringController::class, 'cleanupLogs'])->name('abuse.cleanup');
        Route::post('/abuse/config', [AbuseMonitoringController::class, 'updateConfig'])->name('abuse.config');

        // System Settings (admin only)
        Route::get('/settings', [SettingsController::class, 'index'])
            ->middleware('can:admin-only')
            ->name('settings');
        Route::put('/settings', [SettingsController::class, 'update'])
            ->middleware('can:admin-only')
            ->name('settings.update');

        // Image Management
        Route::get('/images', [App\Http\Controllers\Admin\AdminImageController::class, 'index'])
            ->name('images.index');
        Route::post('/images/{image}/toggle-nsfw', [App\Http\Controllers\Admin\AdminImageController::class, 'toggleNsfw'])
            ->name('images.toggle-nsfw');
        Route::post('/images/bulk-nsfw', [App\Http\Controllers\Admin\AdminImageController::class, 'bulkNsfw'])
            ->name('images.bulk-nsfw');

        // User approval actions (using userId instead of user to avoid conflict)
        Route::get('/users/approval/{userId}', [UserApprovalController::class, 'show'])
            ->middleware('can:admin-only')
            ->name('users.approval.show');
        Route::post('/users/approval/{userId}/approve', [UserApprovalController::class, 'approve'])
            ->middleware('can:admin-only')
            ->name('users.approve');
        Route::post('/users/approval/{userId}/reject', [UserApprovalController::class, 'reject'])
            ->middleware('can:admin-only')
            ->name('users.reject');

        // Database Management
        Route::get('/database', [AdminWebController::class, 'index'])
            ->middleware('can:admin-only')
            ->name('database');

        // Social Media Management (admin only)
        Route::get('/social', [App\Http\Controllers\Admin\SocialMediaController::class, 'index'])
            ->middleware('can:admin-only')
            ->name('social');
        Route::put('/social/config', [App\Http\Controllers\Admin\SocialMediaController::class, 'updateConfig'])
            ->middleware('can:admin-only')
            ->name('social.config');

        // ActivityPub / Fediverse Management (admin only)
        Route::get('/activitypub', [AdminActivityPubController::class, 'index'])
            ->middleware('can:admin-only')
            ->name('activitypub');

        // Federation Management (admin only)
        Route::prefix('federation')->middleware('can:admin-only')->name('federation.')->group(static function (): void {
            // Blocked Instances
            Route::get('/blocked', [AdminFederationController::class, 'blocked'])->name('blocked');
            Route::post('/blocked', [AdminFederationController::class, 'storeBlocked'])->name('blocked.store');
            Route::put('/blocked/{blockedInstance}', [AdminFederationController::class, 'updateBlocked'])->name('blocked.update');
            Route::delete('/blocked/{blockedInstance}', [AdminFederationController::class, 'destroyBlocked'])->name('blocked.destroy');

            // Statistics
            Route::get('/stats', [AdminFederationController::class, 'stats'])->name('stats');
        });
    });
});

// Cypress E2E testing routes (only in local/testing)
if (app()->environment(['local', 'testing'])) {
    Route::post('/__cypress__/token-login', [App\Http\Controllers\CypressAuthController::class, 'login']);
    Route::post('/__cypress__/factory', [App\Http\Controllers\CypressAuthController::class, 'factory']);
    Route::post('/__cypress__/artisan', [App\Http\Controllers\CypressAuthController::class, 'artisan']);
    Route::post('/__cypress__/cleanup', [App\Http\Controllers\CypressAuthController::class, 'cleanup']);
}

// Include auth routes (email verification, password reset, etc.)
require __DIR__ . '/auth.php';
