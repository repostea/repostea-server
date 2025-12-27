<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverActivityPubPost;
use App\Models\Comment;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Models\UserBan;
use App\Models\UserStrike;
use App\Services\ActivityPubService;
use App\Services\TwitterService;
use Artisan;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Log;

/**
 * Main admin controller for dashboard, moderation logs, scheduled commands, and social/federation.
 * Reports are in AdminReportController, legal reports in AdminLegalReportController,
 * posts/comments in AdminPostController, users in AdminUserController.
 */
final class AdminController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the admin dashboard.
     */
    public function index()
    {
        $this->authorize('access-admin');

        $stats = [
            'total_users' => User::count(),
            'total_posts' => Post::count(),
            'total_comments' => Comment::count(),
            'pending_reports' => Report::where('status', 'pending')->count(),
            'active_bans' => UserBan::where('is_active', true)->count(),
            'recent_strikes' => UserStrike::where('created_at', '>=', now()->subDays(7))->count(),
            'telescope_entries' => DB::table('telescope_entries')->count(),
        ];

        // Get system health summary
        $systemController = app(\App\Http\Controllers\AdminWebController::class);
        $systemHealth = $systemController->getSystemHealthSummary();

        $recentReports = Report::with(['reportedBy', 'reportable'])
            ->where('status', 'pending')
            ->latest()
            ->limit(10)
            ->get();

        $recentModerationActions = ModerationLog::with(['moderator', 'targetUser'])
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('admin.dashboard', compact('stats', 'systemHealth', 'recentReports', 'recentModerationActions'));
    }

    // =========================================================================
    // Moderation Logs
    // =========================================================================

    /**
     * View moderation logs with filters.
     */
    public function moderationLogs(Request $request)
    {
        $this->authorize('access-admin');

        $query = ModerationLog::with(['moderator', 'targetUser']);

        // Filter by action
        if ($request->has('action') && $request->get('action') !== '') {
            $query->where('action', $request->get('action'));
        }

        // Filter by moderator
        if ($request->has('moderator_id') && $request->get('moderator_id') !== '') {
            $query->where('moderator_id', $request->get('moderator_id'));
        }

        // Filter by target user
        if ($request->has('target_user_id') && $request->get('target_user_id') !== '') {
            $query->where('target_user_id', $request->get('target_user_id'));
        }

        // Filter by date range
        if ($request->has('date_from') && $request->get('date_from') !== '') {
            $query->where('created_at', '>=', $request->get('date_from') . ' 00:00:00');
        }

        if ($request->has('date_to') && $request->get('date_to') !== '') {
            $query->where('created_at', '<=', $request->get('date_to') . ' 23:59:59');
        }

        // Search by reason
        if ($request->has('search') && $request->get('search') !== '') {
            $query->where('reason', 'like', '%' . $request->get('search') . '%');
        }

        $logs = $query->latest('created_at')->paginate(50);

        // Get distinct actions for filter dropdown
        $actions = ModerationLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        // Get moderators for filter dropdown
        $moderators = User::whereHas('roles', function ($q): void {
            $q->whereIn('slug', ['admin', 'moderator']);
        })
            ->orderBy('username')
            ->get(['id', 'username']);

        return view('admin.logs.index', compact('logs', 'actions', 'moderators'));
    }

    // =========================================================================
    // Scheduled Commands
    // =========================================================================

    /**
     * View scheduled commands (admin only).
     */
    public function scheduledCommands()
    {
        $this->authorize('admin-only');

        return view('admin.scheduled-commands');
    }

    /**
     * Execute a scheduled command manually.
     */
    public function executeCommand(Request $request)
    {
        $this->authorize('admin-only');

        $validated = $request->validate([
            'command' => 'required|string',
            'email' => 'nullable|email',
        ]);

        $command = $validated['command'];
        $email = $validated['email'] ?? null;

        // Check if this is an SSE request (EventSource sends GET requests)
        $isSSE = $request->isMethod('get');

        // Whitelist of allowed commands (base commands and full commands with parameters)
        $allowedCommands = [
            'mbin:import --all --sync --hours=24 --no-interaction',
            'mbin:import',
            'mbin:sync-avatars',
            'mbin:sync-media',
            'votes:recalculate',
            'rate-limit:cleanup --force',
            'rate-limit:cleanup',
            'rate-limit:clear',
            'posts:recalculate-counts --hours=48',
            'posts:recalculate-counts',
            'transparency:calculate',
            'achievements:calculate --recent=12',
            'achievements:calculate --all',
            'achievements:calculate',
            'karma:recalculate-all',
            'karma:recalculate-levels',
            'invitation:create',
            'emails:test',
        ];

        // Extract base command for validation
        $baseCommand = explode(' ', $command)[0];
        $isAllowed = in_array($command, $allowedCommands) || in_array($baseCommand, $allowedCommands);

        if (! $isAllowed) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Command not allowed.',
                ], 403);
            }

            return redirect()->back()->with('error', 'Command not allowed.');
        }

        // If command is emails:test and email is provided, append it
        if ($baseCommand === 'emails:test' && $email) {
            $command = "emails:test {$email}";
        }

        // Handle SSE streaming
        if ($isSSE) {
            return response()->stream(function () use ($command): void {
                try {
                    $process = new \Symfony\Component\Process\Process(
                        array_merge(['php', base_path('artisan')], explode(' ', $command)),
                    );
                    $process->setTimeout(600); // 10 minutes timeout

                    $process->start();

                    foreach ($process as $type => $data) {
                        if ($type === $process::OUT || $type === $process::ERR) {
                            echo 'data: ' . json_encode(['type' => 'output', 'line' => trim($data)]) . "\n\n";
                            ob_flush();
                            flush();
                        }
                    }

                    $exitCode = $process->wait();

                    if ($exitCode === 0) {
                        echo 'data: ' . json_encode(['type' => 'done', 'message' => "Command executed successfully: {$command}"]) . "\n\n";
                    } else {
                        echo 'data: ' . json_encode(['type' => 'error', 'message' => "Command failed with exit code: {$exitCode}"]) . "\n\n";
                    }
                    ob_flush();
                    flush();
                } catch (Exception $e) {
                    Log::error("Error streaming admin command {$command}: " . $e->getMessage());
                    echo 'data: ' . json_encode(['type' => 'error', 'message' => __('messages.admin.command_error')]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }, 200, [
                'Cache-Control' => 'no-cache',
                'Content-Type' => 'text/event-stream',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        // Handle regular request
        try {
            Artisan::call($command);
            $output = Artisan::output();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Command executed successfully: {$command}",
                    'output' => $output,
                ]);
            }

            return redirect()->back()->with('success', "Command executed successfully: {$command}");
        } catch (Exception $e) {
            Log::error("Error executing admin command {$command}: " . $e->getMessage());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.admin.command_error'),
                    'error' => ErrorHelper::getSafeError($e),
                ], 500);
            }

            return redirect()->back()->with('error', __('messages.admin.command_error'));
        }
    }

    // =========================================================================
    // Social / Federation
    // =========================================================================

    /**
     * Post a specific post to Twitter manually.
     */
    public function postToTwitter(Request $request, Post $post, TwitterService $twitterService)
    {
        $this->authorize('admin-only');

        // Check if Twitter is configured
        if (! $twitterService->isConfigured()) {
            return redirect()->back()->with('error', 'Twitter API is not configured. Add credentials to .env file.');
        }

        // Check if already posted
        if ($post->twitter_posted_at !== null) {
            return redirect()->back()->with('error', 'This post has already been published to Twitter.');
        }

        // Check if post is published
        if ($post->status !== 'published') {
            return redirect()->back()->with('error', 'Only published posts can be shared on Twitter.');
        }

        // Set manual posting metadata
        $post->twitter_post_method = 'manual';
        $post->twitter_post_reason = 'admin_action';
        $post->twitter_posted_by = Auth::id();

        // Attempt to post
        $success = $twitterService->postTweet($post);

        if ($success) {
            ModerationLog::logAction(
                moderatorId: Auth::id(),
                action: 'post_to_twitter',
                targetUserId: $post->user_id,
                targetType: 'Post',
                targetId: $post->id,
                metadata: [
                    'tweet_id' => $post->fresh()->twitter_tweet_id,
                    'method' => 'manual',
                ],
            );

            return redirect()->back()->with('success', 'Post published to Twitter successfully!');
        }

        return redirect()->back()->with('error', 'Failed to publish to Twitter. Check logs for details.');
    }

    /**
     * Repost a post to Twitter (even if already posted).
     */
    public function repostToTwitter(Request $request, Post $post, TwitterService $twitterService)
    {
        $this->authorize('admin-only');

        // Check if Twitter is configured
        if (! $twitterService->isConfigured()) {
            return redirect()->back()->with('error', 'Twitter API is not configured. Add credentials to .env file.');
        }

        // Check if post is published
        if ($post->status !== 'published') {
            return redirect()->back()->with('error', 'Only published posts can be shared on Twitter.');
        }

        // Clear previous twitter data to allow reposting
        $previousTweetId = $post->twitter_tweet_id;
        $post->twitter_posted_at = null;
        $post->twitter_tweet_id = null;
        $post->twitter_post_method = 'manual';
        $post->twitter_post_reason = 'repost';
        $post->twitter_posted_by = Auth::id();

        // Attempt to post
        $success = $twitterService->postTweet($post);

        if ($success) {
            ModerationLog::logAction(
                moderatorId: Auth::id(),
                action: 'repost_to_twitter',
                targetUserId: $post->user_id,
                targetType: 'Post',
                targetId: $post->id,
                metadata: [
                    'previous_tweet_id' => $previousTweetId,
                    'new_tweet_id' => $post->fresh()->twitter_tweet_id,
                ],
            );

            return redirect()->back()->with('success', 'Post reposted to Twitter successfully!');
        }

        // Restore previous data if failed
        $post->twitter_posted_at = now();
        $post->twitter_tweet_id = $previousTweetId;
        $post->save();

        return redirect()->back()->with('error', 'Failed to repost to Twitter. Check logs for details.');
    }

    /**
     * Federate a post to ActivityPub (Fediverse).
     */
    public function federatePost(Request $request, Post $post, ActivityPubService $activityPubService): RedirectResponse
    {
        $this->authorize('admin-only');

        // Check if ActivityPub is enabled
        if (! $activityPubService->isEnabled()) {
            return redirect()->back()->with('error', 'ActivityPub is not enabled. Set ACTIVITYPUB_ENABLED=true in .env file.');
        }

        // Check if post is published
        if ($post->status !== 'published') {
            return redirect()->back()->with('error', 'Only published posts can be federated.');
        }

        // Check federation requirements and provide specific error messages
        $postSettings = \App\Models\ActivityPubPostSettings::where('post_id', $post->id)->first();
        if ($postSettings === null || ! $postSettings->should_federate) {
            return redirect()->back()->with('error', 'Post is not marked for federation. The post author needs to enable federation for this post.');
        }

        $userSettings = \App\Models\ActivityPubUserSettings::where('user_id', $post->user_id)->first();
        if ($userSettings === null || ! $userSettings->federation_enabled) {
            $author = $post->user->username ?? 'Unknown';

            return redirect()->back()->with('error', "User @{$author} has not enabled federation in their profile settings.");
        }

        // Check sub federation if post is in a sub
        if ($post->sub_id !== null) {
            $subSettings = \App\Models\ActivityPubSubSettings::where('sub_id', $post->sub_id)->first();
            if ($subSettings !== null && ! $subSettings->federation_enabled) {
                $subName = $post->sub->name ?? 'Unknown';

                return redirect()->back()->with('error', "Community /{$subName} has federation disabled.");
            }
        }

        // Dispatch the federation job
        DeliverActivityPubPost::dispatch($post);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'federate_to_activitypub',
            targetUserId: $post->user_id,
            targetType: 'Post',
            targetId: $post->id,
            metadata: ['post_title' => $post->title],
        );

        return redirect()->back()->with('success', 'Post queued for federation to the Fediverse!');
    }
}
