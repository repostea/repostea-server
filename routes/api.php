<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminPostRelationshipController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AgoraController;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\ApiPasswordController;
use App\Http\Controllers\Api\BlueskyAuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CommentListController;
use App\Http\Controllers\Api\EmailChangeController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\KarmaController;
use App\Http\Controllers\Api\KarmaEventController;
use App\Http\Controllers\Api\LegalReportController;
use App\Http\Controllers\Api\MagicLinkController;
use App\Http\Controllers\Api\MastodonAuthController;
use App\Http\Controllers\Api\MbinAuthController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MediaMetadataController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferencesController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostRelationshipController;
use App\Http\Controllers\Api\PreferencesController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\RankingsController;
use App\Http\Controllers\Api\RealtimeController;
use App\Http\Controllers\Api\RelationshipVoteController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SavedListController;
use App\Http\Controllers\Api\SealController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SubController;
use App\Http\Controllers\Api\SubMembershipController;
use App\Http\Controllers\Api\SubModerationController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\SystemSettingsController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TelegramAuthController;
use App\Http\Controllers\Api\TransparencyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\V1\ActivityFeedController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\ProfileInformationController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;

Route::prefix('v1')->group(static function (): void {

    // Public system settings (no auth required)
    Route::get('/system/settings', [SystemSettingsController::class, 'index']);

    // Public media metadata (no auth required)
    Route::get('/media/twitter-metadata', [MediaMetadataController::class, 'getTwitterMetadata']);
    Route::get('/media/url-metadata', [MediaMetadataController::class, 'getUrlMetadata'])
        ->middleware('throttle:30,1'); // 30 requests per minute

    // Realtime connection tracking (public)
    Route::prefix('realtime')->group(static function (): void {
        Route::post('/connect', [RealtimeController::class, 'connect']);
        Route::post('/heartbeat', [RealtimeController::class, 'heartbeat']);
        Route::post('/disconnect', [RealtimeController::class, 'disconnect']);
        Route::get('/stats', [RealtimeController::class, 'stats']);
    });

    // Auth endpoints
    Route::post('/login', [ApiAuthController::class, 'login'])
        ->middleware('throttle:10,1'); // 10 attempts per minute
    Route::post('/guest-login', [ApiAuthController::class, 'guestLogin'])
        ->middleware('throttle:10,1'); // 10 attempts per minute
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:3,1'); // 3 attempts per minute
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:3,10') // 3 attempts per 10 minutes
        ->name('api.password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:3,10') // 3 attempts per 10 minutes
        ->name('api.password.update');
    Route::get('/reset-password/{token}', static fn ($token) => redirect(config('app.frontend_url') . '/auth/reset-password?token=' . $token))->middleware('guest')->name('api.password.reset');

    Route::post('/magic-link/email', [MagicLinkController::class, 'sendMagicLink'])
        ->middleware('throttle:3,10'); // 3 attempts per 10 minutes
    Route::post('/magic-link/verify', [MagicLinkController::class, 'verifyMagicLink'])
        ->middleware('throttle:5,1'); // 5 attempts per minute
    Route::get('/auth/magic-link/{token}', static fn ($token) => redirect(config('app.frontend_url') . '/auth/magic-link?token=' . $token))->middleware('signed')->name('auth.magic-link.verify');

    // Mastodon/Fediverse authentication
    Route::get('/auth/mastodon/status', [MastodonAuthController::class, 'status']);
    Route::post('/auth/mastodon/redirect', [MastodonAuthController::class, 'redirect'])
        ->middleware('throttle:10,1');
    Route::post('/auth/mastodon/callback', [MastodonAuthController::class, 'callback'])
        ->middleware('throttle:10,1');

    // Mbin/Kbin authentication
    Route::get('/auth/mbin/status', [MbinAuthController::class, 'status']);
    Route::post('/auth/mbin/redirect', [MbinAuthController::class, 'redirect'])
        ->middleware('throttle:10,1');
    Route::post('/auth/mbin/callback', [MbinAuthController::class, 'callback'])
        ->middleware('throttle:10,1');

    // Telegram authentication
    Route::get('/auth/telegram/status', [TelegramAuthController::class, 'status']);
    Route::post('/auth/telegram/callback', [TelegramAuthController::class, 'callback'])
        ->middleware('throttle:10,1'); // 10 attempts per minute

    // Bluesky authentication (API: status + exchange)
    Route::get('/auth/bluesky/status', [BlueskyAuthController::class, 'status']);
    Route::post('/auth/bluesky/exchange', [BlueskyAuthController::class, 'exchange'])
        ->middleware('throttle:10,1');

    // ActivityPub status (public)
    Route::get('/activitypub/status', [App\Http\Controllers\ActivityPubController::class, 'status']);

    // Public ActivityPub actor info
    Route::get('/activitypub/users/{username}', [App\Http\Controllers\MultiActorActivityPubController::class, 'getUserActorInfo']);
    Route::get('/activitypub/groups/{name}', [App\Http\Controllers\MultiActorActivityPubController::class, 'getGroupActorInfo']);

    // Legal reports (DMCA, abuse, etc.) - Public endpoints (no auth required)
    // Rate limited to prevent abuse: max 5 reports per hour, max 20 status checks per hour
    Route::post('/legal-reports', [LegalReportController::class, 'store'])
        ->middleware('throttle:5,60'); // 5 submissions per 60 minutes
    Route::post('/legal-reports/status', [LegalReportController::class, 'status'])
        ->middleware('throttle:20,60'); // 20 status checks per 60 minutes

    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/search', [PostController::class, 'search']);
    Route::get('/posts/frontpage', [PostController::class, 'getFrontpage']);
    Route::get('/posts/pending', [PostController::class, 'getPending']);
    Route::get('/posts/pending/count', [PostController::class, 'getPendingCount']);
    Route::get('/posts/permalink/{uuid}', [PostController::class, 'showByUuid']);

    Route::get('/posts/slug/{slug}', [PostController::class, 'showBySlug']);
    Route::get('/posts/{post}', [PostController::class, 'show']);

    Route::post('/posts/{post}/view', [PostController::class, 'registerView'])
        ->middleware('view.rate.limit');

    Route::post('/posts/impressions', [PostController::class, 'registerImpressions']);

    // Poll routes
    Route::get('/polls/{post}/results', [PollController::class, 'getResults']);
    Route::post('/polls/{post}/vote/{option}', [PollController::class, 'vote']);
    Route::delete('/polls/{post}/vote', [PollController::class, 'removeVote']);

    Route::get('posts/content-type/{contentType}', [PostController::class, 'getByContentType']);

    Route::get('posts/videos', [PostController::class, 'getByContentType'])->defaults('contentType', 'video');
    Route::get('posts/audio', [PostController::class, 'getByContentType'])->defaults('contentType', 'audio');

    Route::post('media/validate', [MediaController::class, 'validateMediaUrl']);
    Route::post('media/info', [MediaController::class, 'getMediaInfo']);

    // Agora (public forum) public routes
    Route::get('/agora', [AgoraController::class, 'index']);
    Route::get('/agora/recent', [AgoraController::class, 'recent']);
    Route::get('/agora/tops', [AgoraController::class, 'tops']);
    Route::get('/agora/{id}', [AgoraController::class, 'show']);

    // Stats endpoints
    Route::get('/stats/general', [StatsController::class, 'general']);
    Route::get('/stats/content', [StatsController::class, 'content']);
    Route::get('/stats/users', [StatsController::class, 'users']);
    Route::get('/stats/engagement', [StatsController::class, 'engagement']);
    Route::get('/stats/trending', [StatsController::class, 'trending']);

    // Transparency endpoints
    Route::get('/transparency', [TransparencyController::class, 'index']);

    // FAQ endpoints
    Route::get('/faq', [FaqController::class, 'index']);

    // Subs endpoints (public)
    Route::get('/subs', [SubController::class, 'index']);
    Route::get('/subs/{nameOrId}', [SubController::class, 'show']);
    Route::get('/subs/{subId}/posts', [SubController::class, 'posts']);
    Route::get('/subs/{subId}/rules', [SubController::class, 'rules']);

    // Preferences endpoints
    Route::get('/preferences', [PreferencesController::class, 'index']);
    Route::post('/preferences', [PreferencesController::class, 'store']);

    Route::get('/comments', [CommentListController::class, 'getAll']);
    Route::get('/posts/{post}/comments', [CommentController::class, 'index']);
    Route::get('/comments/{comment}/vote-stats', [CommentController::class, 'voteStats']);
    Route::get('/comments/recent', [CommentListController::class, 'recent']);
    Route::get('/comments/tops', [CommentListController::class, 'tops']);

    // Post relationships (public read, auth required for write)
    Route::get('/posts/{post}/relationships', [PostRelationshipController::class, 'index']);
    Route::get('/posts/{post}/relationships/continuation-chain', [PostRelationshipController::class, 'continuationChain']);
    Route::get('/relationship-types', [PostRelationshipController::class, 'types']);

    // Relationship votes stats (public read)
    Route::get('/relationships/{relationship}/votes', [RelationshipVoteController::class, 'stats']);

    Route::get('/sync/last-updated', [SyncController::class, 'getLastUpdated']);
    // Activity feed (fisgoneo) - public
    Route::get('/activities/feed', [ActivityFeedController::class, 'index']);

    Route::get('/tags', [TagController::class, 'index']);
    Route::get('/tags/search', [TagController::class, 'search']);
    Route::get('/tags/popular', [TagController::class, 'getPopularTags']);
    Route::get('/tags/category/{categoryId}', [TagController::class, 'getTagsByCategory']);
    Route::get('/tag-categories', [TagController::class, 'getTagCategories']);
    Route::get('/tags/id/{id}', [TagController::class, 'showById']);
    Route::get('/tags/{tag}', [TagController::class, 'show']);
    Route::get('/tags/{tag}/posts', [TagController::class, 'posts']);
    Route::get('/users/search', [UserProfileController::class, 'searchUsers'])
        ->middleware('throttle:30,1'); // 30 searches per minute
    Route::get('/users/by-username/{username}', [UserProfileController::class, 'getByUsername']);
    Route::get('/users/{username}/posts', [UserProfileController::class, 'getUserPosts']);
    Route::get('/users/{username}/comments', [UserProfileController::class, 'getUserComments']);
    Route::get('/users/{user}/karma', [KarmaController::class, 'show']);

    // Rankings endpoints (public)
    Route::get('/rankings/karma', [RankingsController::class, 'karma']);
    Route::get('/rankings/posts', [RankingsController::class, 'posts']);
    Route::get('/rankings/comments', [RankingsController::class, 'comments']);
    Route::get('/rankings/streaks', [RankingsController::class, 'streaks']);
    Route::get('/rankings/achievements', [RankingsController::class, 'achievements']);
    Route::get('/users/{userId}/karma-history', [RankingsController::class, 'userKarmaHistory']);

    // Image serving endpoint (public, with cache)
    // Two routes: one with optional size for backward compatibility, one without
    Route::get('/images/{hash}', [ImageController::class, 'serve']);
    Route::get('/images/{hash}/{size}', [ImageController::class, 'serve']);

    // Email verification endpoint - requires auth but NOT email verification
    Route::middleware('auth:sanctum')->group(static function (): void {
        Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware(['throttle:6,1']);
    });

    // Email change confirmation - public route (user clicks link from email)
    Route::post('/email/change/confirm', [EmailChangeController::class, 'confirm'])
        ->middleware(['throttle:5,1']);

    Route::middleware(['auth:sanctum', 'verified'])->group(static function (): void {

        Route::post('/logout', [ApiAuthController::class, 'logout']);
        Route::get('/user', [ApiAuthController::class, 'user']);
        Route::put('/user/profile-information', [ProfileInformationController::class, 'update']);
        Route::put('/user/password', [ApiPasswordController::class, 'update']);

        // Email change routes
        Route::post('/user/email/change', [EmailChangeController::class, 'request'])->middleware('throttle:3,60');
        Route::delete('/user/email/change', [EmailChangeController::class, 'cancel']);
        Route::get('/user/email/change/status', [EmailChangeController::class, 'status']);

        // Session management routes
        Route::get('/user/sessions', [SessionController::class, 'index']);
        Route::delete('/user/sessions/{tokenId}', [SessionController::class, 'destroy']);
        Route::delete('/user/sessions', [SessionController::class, 'destroyAll']);

        Route::post('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store']);
        Route::delete('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy']);
        Route::get('/user/two-factor-qr-code', [TwoFactorQrCodeController::class, 'show']);
        Route::get('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'index']);
        Route::post('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'store']);

        Route::post('/posts', [PostController::class, 'store'])->middleware('action.rate.limit:create_post');
        Route::post('/posts/import', [PostController::class, 'import'])->middleware('action.rate.limit:create_post');
        Route::put('/posts/{post}', [PostController::class, 'update'])->middleware('action.rate.limit:update_post');
        Route::patch('/posts/{post}/status', [PostController::class, 'updateStatus'])->middleware('action.rate.limit:update_post');
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
        Route::post('/posts/{post}/vote', [PostController::class, 'vote'])->middleware('action.rate.limit:vote');
        Route::delete('/posts/{post}/vote', [PostController::class, 'unvote']);
        Route::get('/posts/{post}/vote-stats', [PostController::class, 'voteStats']);

        // Post relationships (write operations require auth)
        Route::post('/posts/{post}/relationships', [PostRelationshipController::class, 'store'])->middleware('action.rate.limit:create_post');
        Route::delete('/posts/{post}/relationships/{relationship}', [PostRelationshipController::class, 'destroy']);

        // Relationship votes (write operations require auth)
        Route::post('/relationships/{relationship}/vote', [RelationshipVoteController::class, 'vote'])->middleware('action.rate.limit:vote');

        Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->middleware('action.rate.limit:create_comment');

        Route::put('/comments/{comment}', [CommentController::class, 'update'])->middleware('action.rate.limit:update_comment');
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
        Route::post('/comments/{comment}/moderate', [CommentController::class, 'moderate']);
        Route::post('/comments/{comment}/vote', [CommentController::class, 'vote'])->middleware('action.rate.limit:vote');
        Route::delete('/comments/{comment}/vote', [CommentController::class, 'unvote']);

        // Seals system routes
        Route::get('/seals', [SealController::class, 'getUserSeals']);
        Route::post('/posts/{post}/seals', [SealController::class, 'markPost'])->middleware('action.rate.limit:vote');
        Route::delete('/posts/{post}/seals', [SealController::class, 'unmarkPost']);
        Route::get('/posts/{post}/seals', [SealController::class, 'getPostMarks']);
        Route::post('/comments/{comment}/seals', [SealController::class, 'markComment'])->middleware('action.rate.limit:vote');
        Route::delete('/comments/{comment}/seals', [SealController::class, 'unmarkComment']);
        Route::get('/comments/{comment}/seals', [SealController::class, 'getCommentMarks']);
        Route::post('/seals/check', [SealController::class, 'checkUserMarks']);

        // Agora (public forum) routes
        Route::post('/agora', [AgoraController::class, 'store'])->middleware('action.rate.limit:create_comment');
        Route::put('/agora/{id}', [AgoraController::class, 'update'])->middleware('action.rate.limit:update_comment');
        Route::delete('/agora/{id}', [AgoraController::class, 'destroy']);
        Route::post('/agora/{id}/vote', [AgoraController::class, 'vote'])->middleware('action.rate.limit:vote');
        Route::delete('/agora/{id}/vote', [AgoraController::class, 'unvote']);

        // Image upload routes (rate limited: 10 uploads per minute)
        Route::post('/user/avatar', [ImageController::class, 'uploadAvatar'])
            ->middleware('throttle:10,1');
        Route::delete('/user/avatar', [ImageController::class, 'deleteAvatar']);
        Route::post('/posts/{post}/thumbnail', [ImageController::class, 'uploadThumbnail'])
            ->middleware('throttle:10,1');
        Route::post('/posts/{post}/thumbnail-from-url', [ImageController::class, 'uploadThumbnailFromUrl'])
            ->middleware('throttle:10,1');
        Route::delete('/posts/{post}/thumbnail', [ImageController::class, 'deleteThumbnail']);
        Route::post('/images/inline', [ImageController::class, 'uploadInlineImage'])
            ->middleware('throttle:10,1');

        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store'])->middleware('action.rate.limit:send_invitation');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy']);

        Route::get('/karma/levels', [KarmaController::class, 'levels']);
        Route::get('/karma/leaderboard', [KarmaController::class, 'leaderboard']);

        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::get('/profile/moderation-history', [UserController::class, 'moderationHistory']);
        Route::delete('/profile', [UserController::class, 'deleteAccount']);

        // ActivityPub federation settings (user)
        Route::get('/activitypub/settings', [App\Http\Controllers\MultiActorActivityPubController::class, 'getSettings']);
        Route::patch('/activitypub/settings', [App\Http\Controllers\MultiActorActivityPubController::class, 'updateSettings']);

        // ActivityPub federation settings (per post)
        Route::get('/activitypub/posts/{post}/settings', [App\Http\Controllers\MultiActorActivityPubController::class, 'getPostSettings']);
        Route::patch('/activitypub/posts/{post}/settings', [App\Http\Controllers\MultiActorActivityPubController::class, 'updatePostSettings']);

        // ActivityPub federation settings (per sub - moderators only)
        Route::get('/activitypub/subs/{sub}/settings', [App\Http\Controllers\MultiActorActivityPubController::class, 'getSubSettings']);
        Route::patch('/activitypub/subs/{sub}/settings', [App\Http\Controllers\MultiActorActivityPubController::class, 'updateSubSettings']);
        Route::get('/activitypub/subs/{sub}/announceable', [App\Http\Controllers\MultiActorActivityPubController::class, 'getAnnounceablePosts']);
        Route::post('/activitypub/subs/{sub}/announce/{post}', [App\Http\Controllers\MultiActorActivityPubController::class, 'announcePost']);

        Route::post('/sync/posts', [SyncController::class, 'syncPosts']);

        Route::get('/lists', [SavedListController::class, 'index']);
        Route::post('/lists', [SavedListController::class, 'store']);

        // All modification operations use UUID-based routes
        // Note: These must come before {username}/{slug} to avoid UUID being matched as username
        Route::get('/lists/{identifier}', [SavedListController::class, 'show']);
        Route::put('/lists/{identifier}', [SavedListController::class, 'update']);
        Route::delete('/lists/{identifier}', [SavedListController::class, 'destroy']);
        Route::get('/lists/{identifier}/posts', [SavedListController::class, 'posts'])->name('lists.posts');
        Route::post('/lists/{identifier}/posts', [SavedListController::class, 'addPost']);
        Route::delete('/lists/{identifier}/posts', [SavedListController::class, 'removePost']);
        Route::put('/lists/{identifier}/posts/notes', [SavedListController::class, 'updatePostNotes']);
        Route::delete('/lists/{identifier}/posts/all', [SavedListController::class, 'clearList']);

        // Route for viewing custom lists with username/slug format (read-only, SEO-friendly)
        // Must come after /lists/{identifier} routes
        Route::get('/lists/{username}/{slug}', [SavedListController::class, 'showByUsernameAndSlug']);

        Route::post('/posts/toggle-favorite', [SavedListController::class, 'toggleFavorite']);
        Route::post('/posts/toggle-read-later', [SavedListController::class, 'toggleReadLater']);
        Route::get('/posts/{post}/saved-status', [SavedListController::class, 'checkSavedStatus']);

        // Subs endpoints (authenticated)
        Route::post('/subs', [SubController::class, 'store'])->middleware('action.rate.limit:create_sub');
        Route::put('/subs/{subId}', [SubController::class, 'update'])->middleware('action.rate.limit:update_profile,10');
        Route::delete('/subs/{subId}', [SubController::class, 'destroy']);
        Route::post('/subs/{subId}/icon', [SubController::class, 'uploadIcon']);

        // Sub membership endpoints
        Route::post('/subs/{subId}/join', [SubMembershipController::class, 'join']);
        Route::post('/subs/{subId}/leave', [SubMembershipController::class, 'leave']);
        Route::post('/subs/{subId}/membership-requests', [SubMembershipController::class, 'createMembershipRequest']);
        Route::get('/subs/{subId}/members', [SubMembershipController::class, 'members']);
        Route::delete('/subs/{subId}/members/{userId}', [SubMembershipController::class, 'removeMember']);
        Route::get('/subs/{subId}/membership-requests', [SubMembershipController::class, 'membershipRequests']);
        Route::post('/subs/{subId}/membership-requests/{userId}/approve', [SubMembershipController::class, 'approveMembershipRequest']);
        Route::post('/subs/{subId}/membership-requests/{userId}/reject', [SubMembershipController::class, 'rejectMembershipRequest']);

        // Sub moderation endpoints
        Route::get('/subs/{subId}/pending-posts', [SubModerationController::class, 'pendingPosts']);
        Route::post('/subs/{subId}/posts/{postId}/approve', [SubModerationController::class, 'approvePost']);
        Route::post('/subs/{subId}/posts/{postId}/reject', [SubModerationController::class, 'rejectPost']);

        // Sub moderators management
        Route::get('/subs/{subId}/moderators', [SubModerationController::class, 'moderators']);
        Route::post('/subs/{subId}/moderators', [SubModerationController::class, 'addModerator']);
        Route::delete('/subs/{subId}/moderators/{userId}', [SubModerationController::class, 'removeModerator']);

        // Sub content moderation (hide/unhide posts)
        Route::get('/subs/{subId}/hidden-posts', [SubModerationController::class, 'hiddenPosts']);
        Route::post('/subs/{subId}/posts/{postId}/hide', [SubModerationController::class, 'hidePost']);
        Route::post('/subs/{subId}/posts/{postId}/unhide', [SubModerationController::class, 'unhidePost']);

        // Sub ownership claim (for orphaned subs)
        Route::get('/subs/{subId}/claim-status', [SubModerationController::class, 'claimStatus']);
        Route::post('/subs/{subId}/claim', [SubModerationController::class, 'claimOwnership']);

        // Notifications endpoints (authenticated)
        Route::get('/notifications/summary', [NotificationController::class, 'getSummary']);
        Route::get('/notifications', [NotificationController::class, 'index']);

        // Push subscription management
        Route::get('/notifications/vapid-public-key', [PushSubscriptionController::class, 'getVapidPublicKey']);
        Route::get('/notifications/push-subscriptions', [PushSubscriptionController::class, 'getPushSubscriptions']);
        Route::post('/notifications/push-subscription', [PushSubscriptionController::class, 'savePushSubscription']);
        Route::delete('/notifications/push-subscription', [PushSubscriptionController::class, 'removePushSubscription']);
        Route::post('/notifications/test-push', [PushSubscriptionController::class, 'sendTestPush'])
            ->middleware('throttle:3,1'); // Max 3 test notifications per minute

        // Notification preferences
        Route::get('/notifications/preferences', [NotificationPreferencesController::class, 'getNotificationPreferences']);
        Route::put('/notifications/preferences', [NotificationPreferencesController::class, 'updateNotificationPreferences']);

        // Snooze endpoints
        Route::post('/notifications/snooze', [NotificationPreferencesController::class, 'snooze']);
        Route::delete('/notifications/snooze', [NotificationPreferencesController::class, 'unsnooze']);

        // Individual notification management
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/categories/{category}/view', [NotificationController::class, 'updateViewTimestamp']);
        Route::delete('/notifications/all', [NotificationController::class, 'destroyAll']);
        Route::delete('/notifications/old', [NotificationController::class, 'destroyOld']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        // Reports endpoints (authenticated)
        Route::post('/reports', [ReportController::class, 'store'])->middleware('action.rate.limit:create_report');
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/{report}', [ReportController::class, 'show']);
        Route::delete('/reports/{report}', [ReportController::class, 'destroy']);

        // Admin endpoints (authenticated, admin only)
        Route::prefix('admin')->middleware('admin')->group(static function (): void {
            Route::apiResource('karma-events', KarmaEventController::class);
            Route::post('karma-events/{id}/notify', [KarmaEventController::class, 'notify']);
            Route::post('rankings/clear-cache', [RankingsController::class, 'clearCache']);

            // Post Relationships Management
            Route::get('post-relationships', [AdminPostRelationshipController::class, 'index']);
            Route::get('post-relationships/statistics', [AdminPostRelationshipController::class, 'statistics']);
            Route::get('post-relationships/audit', [AdminPostRelationshipController::class, 'audit']);
            Route::post('post-relationships/cleanup', [AdminPostRelationshipController::class, 'cleanup']);
            Route::post('post-relationships/bulk-destroy', [AdminPostRelationshipController::class, 'bulkDestroy']);
            Route::delete('post-relationships/{relationship}', [AdminPostRelationshipController::class, 'destroy']);

            // Federation settings management
            Route::get('federation/settings', [App\Http\Controllers\Admin\SettingsController::class, 'getFederationSettings']);
            Route::patch('federation/settings', [App\Http\Controllers\Admin\SettingsController::class, 'updateFederationSettings']);

            // Blocked instances management
            Route::get('federation/blocked-instances', [App\Http\Controllers\Admin\BlockedInstanceController::class, 'index']);
            Route::post('federation/blocked-instances', [App\Http\Controllers\Admin\BlockedInstanceController::class, 'store']);
            Route::get('federation/blocked-instances/check', [App\Http\Controllers\Admin\BlockedInstanceController::class, 'check']);
            Route::patch('federation/blocked-instances/{blockedInstance}', [App\Http\Controllers\Admin\BlockedInstanceController::class, 'update']);
            Route::delete('federation/blocked-instances/{blockedInstance}', [App\Http\Controllers\Admin\BlockedInstanceController::class, 'destroy']);

            // Federation statistics dashboard
            Route::get('federation/stats', [App\Http\Controllers\Admin\FederationStatsController::class, 'index']);
            Route::get('federation/stats/engaged-posts', [App\Http\Controllers\Admin\FederationStatsController::class, 'engagedPosts']);
            Route::get('federation/stats/instances', [App\Http\Controllers\Admin\FederationStatsController::class, 'followersByInstance']);
            Route::get('federation/stats/deliveries', [App\Http\Controllers\Admin\FederationStatsController::class, 'deliveryStats']);
            Route::get('federation/stats/failures', [App\Http\Controllers\Admin\FederationStatsController::class, 'recentFailures']);
        });
    });

    // Admin endpoints that need to work with web sessions (for Blade pages)
    // These are placed here (still inside v1 group) but use web middleware for session auth
    Route::prefix('admin')
        ->middleware([
            Illuminate\Cookie\Middleware\EncryptCookies::class,
            Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            Illuminate\Session\Middleware\StartSession::class,
            Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            'auth:web',
            'admin',
        ])
        ->group(static function (): void {
            // Database management
            Route::get('database/stats', [AdminController::class, 'getDatabaseStats']);
            Route::post('database/backup', [AdminController::class, 'createBackup']);

            // System management
            Route::post('clear-cache/{type}', [App\Http\Controllers\AdminWebController::class, 'clearCache']);
            Route::post('database/backup/{database}', [AdminController::class, 'createSingleBackup'])
                ->where('database', 'main|media');
        });
});
