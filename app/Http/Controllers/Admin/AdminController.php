<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverActivityPubPost;
use App\Models\Comment;
use App\Models\LegalReport;
use App\Models\LegalReportNote;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Report;
use App\Models\ReportNote;
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
use Illuminate\Support\Facades\Mail;
use Log;

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

    /**
     * List all users with filters.
     */
    public function users(Request $request)
    {
        $this->authorize('access-admin');

        $query = User::with(['roles', 'bans' => fn ($q) => $q->where('is_active', true)])
            ->withCount(['posts', 'comments', 'votes']);

        // Search (case-insensitive)
        if ($request->has('search') && ! empty($request->get('search'))) {
            $search = strtolower($request->get('search'));
            $query->where(function ($q) use ($search): void {
                $q->whereRaw('LOWER(username) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        // Filter by ban status
        if ($request->has('banned') && $request->get('banned') === '1') {
            $query->whereHas('bans', fn ($q) => $q->where('is_active', true));
        }

        // Filter by role
        if ($request->has('role') && ! empty($request->get('role'))) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $request->get('role')));
        }

        // Filter by email verification status
        if ($request->has('verified')) {
            if ($request->get('verified') === '1') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->get('verified') === '0') {
                $query->whereNull('email_verified_at');
            }
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        // Validate sort direction
        if (! in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        // Apply sorting based on column
        switch ($sortBy) {
            case 'username':
                $query->orderBy('username', $sortDirection);
                break;
            case 'karma':
                $query->orderBy('karma_points', $sortDirection);
                break;
            case 'status':
                // Sort by banned status (users with active bans first/last)
                $query->leftJoin('user_bans', function ($join): void {
                    $join->on('users.id', '=', 'user_bans.user_id')
                        ->where('user_bans.is_active', '=', true);
                })
                    ->select('users.*')
                    ->selectRaw('CASE WHEN user_bans.id IS NOT NULL THEN 1 ELSE 0 END as is_banned')
                    ->orderBy('is_banned', $sortDirection)
                    ->orderBy('created_at', 'desc'); // Secondary sort by creation date
                break;
            case 'created_at':
            default:
                $query->orderBy('created_at', $sortDirection);
                break;
        }

        $users = $query->paginate(50)->appends($request->except('page'));

        // Get only admin and moderator roles for the filter dropdown
        $roles = \App\Models\Role::whereIn('slug', ['admin', 'moderator'])->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show user details and moderation history.
     */
    public function showUser(User $user)
    {
        $this->authorize('access-admin');

        $user->load([
            'roles',
            'bans' => fn ($q) => $q->latest(),
            'strikes' => fn ($q) => $q->latest(),
            'posts' => fn ($q) => $q->latest()->limit(10),
            'comments' => fn ($q) => $q->latest()->limit(10),
            'achievements',
        ]);

        $moderationLogs = ModerationLog::where('target_user_id', $user->id)
            ->with('moderator')
            ->latest('created_at')
            ->paginate(20);

        // Get email change history
        $emailChanges = ModerationLog::where('target_user_id', $user->id)
            ->where('action', 'email_changed')
            ->latest('created_at')
            ->get();

        // Only allow assigning moderator and admin roles
        $allRoles = \App\Models\Role::whereIn('slug', ['moderator', 'admin'])->get();

        // Get achievements for assignment
        // Special: Only collaborator achievements (manual assignment)
        $collaboratorAchievements = \App\Models\Achievement::where('slug', 'like', 'collaborator_%')->orderBy('slug')->get();
        // Normal: All other achievements (mostly automatic)
        $normalAchievements = \App\Models\Achievement::where('slug', 'not like', 'collaborator_%')->orderBy('type')->orderBy('name')->get();

        return view('admin.users.show', compact('user', 'moderationLogs', 'allRoles', 'collaboratorAchievements', 'normalAchievements', 'emailChanges'));
    }

    /**
     * Ban a user.
     */
    public function banUser(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'type' => 'required|in:temporary,permanent,shadowban',
            'reason' => 'required|string|max:1000',
            'internal_notes' => 'nullable|string|max:2000',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        $expiresAt = null;
        if ($validated['type'] === 'temporary' && isset($validated['duration_days']) && $validated['duration_days'] !== '') {
            $expiresAt = now()->addDays((int) $validated['duration_days']);
        }

        $ban = UserBan::create([
            'user_id' => $user->id,
            'banned_by' => Auth::id(),
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'internal_notes' => $validated['internal_notes'] ?? null,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        // Log the action
        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'ban_user',
            targetUserId: $user->id,
            reason: $validated['reason'],
            metadata: [
                'ban_id' => $ban->id,
                'type' => $validated['type'],
                'expires_at' => $expiresAt?->toDateTimeString(),
            ],
        );

        return redirect()->back()->with('success', "User {$user->username} has been banned.");
    }

    /**
     * Unban a user.
     */
    public function unbanUser(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $activeBan = $user->bans()->where('is_active', true)->first();

        if (! $activeBan) {
            return redirect()->back()->with('error', 'User is not banned.');
        }

        $activeBan->update(['is_active' => false]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'unban_user',
            targetUserId: $user->id,
            metadata: ['ban_id' => $activeBan->id],
        );

        return redirect()->back()->with('success', "User {$user->username} has been unbanned.");
    }

    /**
     * Permanently delete a user and all their data.
     * Only admins can perform this action.
     */
    public function destroyUser(User $user)
    {
        $this->authorize('admin-only');

        // Prevent deleting yourself
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        // Prevent deleting other admins
        if ($user->hasRole('admin')) {
            return redirect()->back()->with('error', 'You cannot delete other administrator accounts.');
        }

        $username = $user->username;

        try {
            DB::beginTransaction();

            // Log the deletion before actually deleting
            ModerationLog::logAction(
                moderatorId: Auth::id(),
                action: 'delete_user',
                targetUserId: $user->id,
                metadata: [
                    'username' => $username,
                    'email' => $user->email,
                    'posts_count' => $user->posts()->count(),
                    'comments_count' => $user->comments()->count(),
                ],
            );

            // Delete the user (cascade will handle related data based on DB constraints)
            $user->delete();

            DB::commit();

            return redirect()->route('admin.users')->with('success', "User {$username} has been deleted. Can be restored within 15 days from Deleted Users.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting user: ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while deleting the user. Please try again.');
        }
    }

    /**
     * Show deleted users.
     */
    public function deletedUsers(Request $request)
    {
        $this->authorize('admin-only');

        $query = User::onlyTrashed();

        // Search
        if ($request->has('search') && ! empty($request->get('search'))) {
            $search = strtolower($request->get('search'));
            $query->where(function ($q) use ($search): void {
                $q->whereRaw('LOWER(username) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        $users = $query->orderBy('deleted_at', 'desc')->paginate(50)->appends($request->except('page'));

        return view('admin.users.deleted', compact('users'));
    }

    /**
     * Restore a deleted user.
     */
    public function restoreUser(int $id)
    {
        $this->authorize('admin-only');

        $user = User::onlyTrashed()->findOrFail($id);
        $username = $user->username;

        $user->restore();

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'restore_user',
            targetUserId: $user->id,
            metadata: ['username' => $username],
        );

        return redirect()->route('admin.users.deleted')->with('success', "User {$username} has been restored successfully.");
    }

    /**
     * Permanently delete a user (force delete).
     */
    public function forceDeleteUser(int $id)
    {
        $this->authorize('admin-only');

        $user = User::onlyTrashed()->findOrFail($id);
        $username = $user->username;

        try {
            DB::beginTransaction();

            // Log before permanent deletion
            ModerationLog::logAction(
                moderatorId: Auth::id(),
                action: 'force_delete_user',
                targetUserId: $user->id,
                metadata: [
                    'username' => $username,
                    'email' => $user->email,
                    'deleted_at' => $user->deleted_at->toDateTimeString(),
                ],
            );

            // Permanent delete
            $user->forceDelete();

            DB::commit();

            return redirect()->route('admin.users.deleted')->with('success', "User {$username} has been permanently deleted.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error force deleting user: ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while permanently deleting the user.');
        }
    }

    /**
     * Give a strike to a user.
     */
    public function giveStrike(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'type' => 'required|in:warning,minor,major,critical',
            'reason' => 'required|string|max:1000',
            'internal_notes' => 'nullable|string|max:2000',
            'related_post_id' => 'nullable|exists:posts,id',
            'related_comment_id' => 'nullable|exists:comments,id',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        $expiresAt = null;
        if (isset($validated['duration_days']) && $validated['duration_days'] !== '') {
            $expiresAt = now()->addDays((int) $validated['duration_days']);
        }

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => Auth::id(),
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'internal_notes' => $validated['internal_notes'] ?? null,
            'related_post_id' => $validated['related_post_id'] ?? null,
            'related_comment_id' => $validated['related_comment_id'] ?? null,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'give_strike',
            targetUserId: $user->id,
            reason: $validated['reason'],
            metadata: [
                'strike_id' => $strike->id,
                'type' => $validated['type'],
                'expires_at' => $expiresAt?->toDateTimeString(),
            ],
        );

        return redirect()->back()->with('success', "Strike given to user {$user->username}.");
    }

    /**
     * Remove a strike from a user.
     */
    public function removeStrike(Request $request, UserStrike $strike)
    {
        $this->authorize('access-admin');

        $strike->update(['is_active' => false]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'remove_strike',
            targetUserId: $strike->user_id,
            metadata: ['strike_id' => $strike->id],
        );

        return redirect()->back()->with('success', 'Strike has been removed.');
    }

    /**
     * Edit a ban.
     */
    public function editBan(Request $request, UserBan $ban)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'type' => 'required|in:temporary,permanent,shadowban',
            'reason' => 'required|string|max:1000',
            'internal_notes' => 'nullable|string|max:2000',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        // Store old values for logging
        $oldValues = [
            'type' => $ban->type,
            'reason' => $ban->reason,
            'internal_notes' => $ban->internal_notes,
            'expires_at' => $ban->expires_at?->toDateTimeString(),
        ];

        // Calculate new expiration
        $expiresAt = null;
        if ($validated['type'] === 'temporary' && isset($validated['duration_days']) && $validated['duration_days'] !== '') {
            $expiresAt = now()->addDays((int) $validated['duration_days']);
        }

        // Update ban
        $ban->update([
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'internal_notes' => $validated['internal_notes'] ?? null,
            'expires_at' => $expiresAt,
        ]);

        // Store new values for logging
        $newValues = [
            'type' => $ban->type,
            'reason' => $ban->reason,
            'internal_notes' => $ban->internal_notes,
            'expires_at' => $ban->expires_at?->toDateTimeString(),
        ];

        // Log the edit action
        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'edit_ban',
            targetUserId: $ban->user_id,
            reason: 'Ban modified',
            metadata: [
                'ban_id' => $ban->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ],
        );

        return redirect()->back()->with('success', 'Ban has been updated.');
    }

    /**
     * Edit a strike.
     */
    public function editStrike(Request $request, UserStrike $strike)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'type' => 'required|in:warning,minor,major,critical',
            'reason' => 'required|string|max:1000',
            'internal_notes' => 'nullable|string|max:2000',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        // Store old values for logging
        $oldValues = [
            'type' => $strike->type,
            'reason' => $strike->reason,
            'internal_notes' => $strike->internal_notes,
            'expires_at' => $strike->expires_at?->toDateTimeString(),
        ];

        // Calculate new expiration
        $expiresAt = null;
        if (isset($validated['duration_days']) && $validated['duration_days'] !== '') {
            $expiresAt = now()->addDays((int) $validated['duration_days']);
        }

        // Update strike
        $strike->update([
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'internal_notes' => $validated['internal_notes'] ?? null,
            'expires_at' => $expiresAt,
        ]);

        // Store new values for logging
        $newValues = [
            'type' => $strike->type,
            'reason' => $strike->reason,
            'internal_notes' => $strike->internal_notes,
            'expires_at' => $strike->expires_at?->toDateTimeString(),
        ];

        // Log the edit action
        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'edit_strike',
            targetUserId: $strike->user_id,
            reason: 'Strike modified',
            metadata: [
                'strike_id' => $strike->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ],
        );

        return redirect()->back()->with('success', 'Strike has been updated.');
    }

    /**
     * List all posts with moderation filters.
     */
    public function posts(Request $request)
    {
        $this->authorize('access-admin');

        $query = Post::with(['user', 'moderatedBy'])
            ->withCount([
                'views as total_visitors',
                'views as identified_visitors' => function ($q): void {
                    $q->whereNotNull('user_id');
                },
            ]);

        // Filter by user ID (from links)
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Filter by username (from search field)
        if ($request->filled('username')) {
            $query->whereHas('user', function ($q) use ($request): void {
                $q->where('username', 'LIKE', '%' . $request->get('username') . '%');
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Search in title and content (case-insensitive)
        if ($request->has('search') && ! empty($request->get('search'))) {
            $search = strtolower($request->get('search'));
            $query->where(function ($q) use ($search): void {
                $q->whereRaw('LOWER(title) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(content) LIKE ?', ["%{$search}%"]);
            });
        }

        // Get all posts before sorting (needed for aggregate columns)
        $posts = $query->get();

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        // Validate sort column
        $allowedSorts = ['created_at', 'updated_at', 'views', 'total_visitors', 'identified_visitors'];
        if (! in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        // Validate direction
        $sortAsc = $request->get('direction', 'desc') === 'asc';

        // Sort the collection
        $posts = $sortAsc
            ? $posts->sortBy($sortBy)
            : $posts->sortByDesc($sortBy);

        // Paginate manually
        $perPage = 50;
        $currentPage = $request->get('page', 1);
        $posts = new \Illuminate\Pagination\LengthAwarePaginator(
            $posts->forPage($currentPage, $perPage),
            $posts->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.posts.index', compact('posts'));
    }

    /**
     * View post details.
     */
    public function viewPost(Post $post)
    {
        $this->authorize('access-admin');

        // Load post with nested comments (parent comments with their replies)
        $post->load([
            'user',
            'tags',
            'moderatedBy',
            'comments' => function ($query): void {
                $query->whereNull('parent_id') // Only top-level comments
                    ->with([
                        'user',
                        'replies' => fn ($q) => $q->oldest(),
                        'replies.user',
                        'replies.replies' => fn ($q) => $q->oldest(),
                        'replies.replies.user',
                    ]) // Load nested replies
                    ->oldest();
            },
        ]);

        // Get user agent statistics
        $userAgents = DB::table('post_views')
            ->select(
                'user_agent',
                DB::raw('COUNT(*) as total_visitors'),
                DB::raw('COUNT(DISTINCT user_id) as identified_visitors'),
                DB::raw('SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as anonymous_visitors'),
                DB::raw('CASE WHEN LOWER(user_agent) NOT LIKE "%chrome%" AND LOWER(user_agent) NOT LIKE "%firefox%" AND LOWER(user_agent) NOT LIKE "%safari%" AND LOWER(user_agent) NOT LIKE "%edge%" THEN 1 ELSE 0 END as is_unusual'),
            )
            ->where('post_id', $post->id)
            ->whereNotNull('user_agent')
            ->groupBy('user_agent')
            ->get();

        return view('admin.posts.show', compact('post', 'userAgents'));
    }

    /**
     * Update post moderation settings (language and NSFW).
     */
    public function updateModeration(Request $request, Post $post)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'language_code' => 'required|string|max:10',
        ]);

        // Checkboxes: if not present in request, they are unchecked (false)
        $post->update([
            'language_code' => $validated['language_code'],
            'is_nsfw' => $request->has('is_nsfw') ? true : false,
            'language_locked_by_admin' => $request->has('lock_language') ? true : false,
            'nsfw_locked_by_admin' => $request->has('lock_nsfw') ? true : false,
            'moderated_by' => Auth::id(),
            'moderated_at' => now(),
        ]);

        return redirect()
            ->route('admin.posts.view', $post)
            ->with('success', 'Moderation settings updated successfully');
    }

    /**
     * Hide a post (unpublish or pre-moderate).
     */
    public function hidePost(Request $request, Post $post)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $currentStatus = $post->status;

        // For drafts: keep as draft but mark as moderated (pre-moderation)
        // For published: change to hidden
        $newStatus = $currentStatus === 'draft' ? 'draft' : 'hidden';

        $post->update([
            'status' => $newStatus,
            'previous_status' => $currentStatus === 'draft' ? null : $currentStatus, // Only save for published posts
            'moderated_by' => Auth::id(),
            'moderation_reason' => $validated['reason'],
            'moderated_at' => now(),
        ]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: $currentStatus === 'draft' ? 'premoderate_post' : 'hide_post',
            targetUserId: $post->user_id,
            targetType: 'Post',
            targetId: $post->id,
            reason: $validated['reason'],
            metadata: [
                'original_status' => $currentStatus,
                'new_status' => $newStatus,
            ],
        );

        $message = $currentStatus === 'draft'
            ? 'Draft has been pre-moderated. It will be unpublished automatically if user tries to publish it.'
            : 'Post has been unpublished successfully.';

        return redirect()->back()->with('success', $message);
    }

    /**
     * Show/restore a post (remove moderation).
     */
    public function showPost(Request $request, Post $post)
    {
        $this->authorize('access-admin');

        $currentStatus = $post->status;

        // If it's a draft (pre-moderated), keep as draft
        // If it's hidden, restore to previous status or 'published'
        if ($currentStatus === 'draft') {
            // Pre-moderated draft - just remove moderation
            $restoreStatus = 'draft';
        } else {
            // Hidden post - restore to previous status
            $restoreStatus = $post->previous_status ?? 'published';
        }

        $post->update([
            'status' => $restoreStatus,
            'previous_status' => null,
            'moderated_by' => null,
            'moderation_reason' => null,
            'moderated_at' => null,
        ]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'remove_moderation',
            targetUserId: $post->user_id,
            targetType: 'Post',
            targetId: $post->id,
            metadata: [
                'was_status' => $currentStatus,
                'restored_to_status' => $restoreStatus,
            ],
        );

        $message = $restoreStatus === 'draft'
            ? 'Pre-moderation removed. Draft can now be published normally.'
            : 'Post has been republished and is now visible again.';

        return redirect()->back()->with('success', $message);
    }

    /**
     * Approve a pending post and publish it.
     */
    public function approvePost(Request $request, Post $post)
    {
        $this->authorize('access-admin');

        if ($post->status !== 'pending') {
            return redirect()->back()->with('error', 'Post is not pending approval.');
        }

        $post->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'approve_post',
            targetUserId: $post->user_id,
            targetType: 'Post',
            targetId: $post->id,
            metadata: [
                'title' => $post->title,
                'source' => $post->source,
            ],
        );

        return redirect()->back()->with('success', 'Post has been approved and published.');
    }

    /**
     * Delete a post permanently.
     */
    public function deletePost(Request $request, Post $post)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'delete_post',
            targetUserId: $post->user_id,
            targetType: 'Post',
            targetId: $post->id,
            reason: $validated['reason'],
            metadata: [
                'title' => $post->title,
                'url' => $post->url,
            ],
        );

        $post->delete();

        return redirect()->route('admin.posts')->with('success', 'Post has been deleted.');
    }

    /**
     * List all comments with moderation filters.
     */
    public function comments(Request $request)
    {
        $this->authorize('access-admin');

        $query = Comment::with(['user', 'post', 'moderatedBy']);

        // Filter by user ID (from links)
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Filter by username (from search field)
        if ($request->filled('username')) {
            $query->whereHas('user', function ($q) use ($request): void {
                $q->where('username', 'LIKE', '%' . $request->get('username') . '%');
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Search (case-insensitive)
        if ($request->has('search') && ! empty($request->get('search'))) {
            $search = strtolower($request->get('search'));
            $query->whereRaw('LOWER(content) LIKE ?', ["%{$search}%"]);
        }

        $comments = $query->latest()->paginate(50)->appends($request->except('page'));

        return view('admin.comments.index', compact('comments'));
    }

    /**
     * Hide a comment (unpublish).
     */
    public function hideComment(Request $request, Comment $comment)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $comment->update([
            'status' => 'hidden',
            'moderated_by' => Auth::id(),
            'moderation_reason' => $validated['reason'],
            'moderated_at' => now(),
        ]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'hide_comment',
            targetUserId: $comment->user_id,
            targetType: 'Comment',
            targetId: $comment->id,
            reason: $validated['reason'],
        );

        // Apply karma penalty
        $comment->user->updateKarma(-10);
        $comment->user->recordKarma(-10, 'comment_moderated', $comment->id, $validated['reason']);

        return redirect()->back()->with('success', 'Comment has been hidden.');
    }

    /**
     * Show a hidden comment.
     */
    public function showComment(Request $request, Comment $comment)
    {
        $this->authorize('access-admin');

        $comment->update([
            'status' => 'published',
            'moderated_by' => null,
            'moderation_reason' => null,
            'moderated_at' => null,
        ]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'show_comment',
            targetUserId: $comment->user_id,
            targetType: 'Comment',
            targetId: $comment->id,
        );

        // Restore karma penalty (if we want to)
        $comment->user->updateKarma(10);
        $comment->user->recordKarma(10, 'comment_restored', $comment->id, 'Comment moderation was reversed');

        return redirect()->back()->with('success', 'Comment has been restored.');
    }

    /**
     * Delete a comment permanently.
     */
    public function deleteComment(Request $request, Comment $comment)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'delete_comment',
            targetUserId: $comment->user_id,
            targetType: 'Comment',
            targetId: $comment->id,
            reason: $validated['reason'],
            metadata: [
                'content' => $comment->content,
                'post_id' => $comment->post_id,
            ],
        );

        // Apply karma penalty (if not already hidden)
        if ($comment->status !== 'hidden') {
            $comment->user->updateKarma(-10);
            $comment->user->recordKarma(-10, 'comment_deleted', $comment->id, $validated['reason']);
        }

        $comment->delete();

        return redirect()->back()->with('success', 'Comment has been deleted.');
    }

    /**
     * List all reports.
     */
    public function reports(Request $request)
    {
        $this->authorize('access-admin');

        $query = Report::with(['reportedBy', 'reportedUser', 'reportable', 'reviewedBy']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by reason
        if ($request->filled('reason')) {
            $query->where('reason', $request->get('reason'));
        }

        // Filter by type (reportable_type)
        if ($request->filled('type')) {
            $modelMap = [
                'post' => Post::class,
                'comment' => Comment::class,
                'user' => User::class,
            ];
            $type = $request->get('type');
            if (isset($modelMap[$type])) {
                $query->where('reportable_type', $modelMap[$type]);
            }
        }

        $reports = $query->latest()->paginate(50);

        return view('admin.reports.index', compact('reports'));
    }

    /**
     * Resolve a report.
     */
    public function resolveReport(Request $request, Report $report)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $report->resolve(Auth::id(), $validated['notes'] ?? null);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'resolve_report',
            targetUserId: $report->reported_user_id,
            metadata: ['report_id' => $report->id],
        );

        return redirect()->back()->with('success', 'Report has been resolved.');
    }

    /**
     * Dismiss a report.
     */
    public function dismissReport(Request $request, Report $report)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $report->dismiss(Auth::id(), $validated['notes'] ?? null);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'dismiss_report',
            metadata: ['report_id' => $report->id],
        );

        return redirect()->back()->with('success', 'Report has been dismissed.');
    }

    /**
     * Reopen a resolved or dismissed report.
     */
    public function reopenReport(Request $request, Report $report)
    {
        $this->authorize('access-admin');

        // Check if report can be reopened
        if (! $report->canBeReopened()) {
            return redirect()->back()->with('error', 'Only resolved or dismissed reports can be reopened.');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $report->reopen(Auth::id(), $validated['notes'] ?? null);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'reopen_report',
            targetUserId: $report->reported_user_id,
            metadata: ['report_id' => $report->id],
        );

        return redirect()->back()->with('success', 'Report has been reopened and is now pending review.');
    }

    /**
     * View detailed information about a specific report.
     */
    public function viewReport(Report $report)
    {
        $this->authorize('access-admin');

        // Load relationships
        $report->load([
            'reportable',
            'reportedBy',
            'reportedUser',
            'reviewedBy',
            'notes.user',
        ]);

        return view('admin.reports.view', compact('report'));
    }

    /**
     * Add an internal note to a report.
     */
    public function addReportNote(Request $request, Report $report)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'note' => 'required|string|max:5000',
        ]);

        ReportNote::create([
            'report_id' => $report->id,
            'user_id' => Auth::id(),
            'note' => $validated['note'],
        ]);

        return redirect()->back()->with('success', 'Internal note added successfully.');
    }

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

    /**
     * Update user invitation limit.
     */
    public function updateInvitationLimit(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'invitation_limit' => 'required|integer|min:0|max:10000',
        ]);

        $oldLimit = $user->invitation_limit ?? 'default (' . $user->getInvitationLimit() . ')';

        $user->update([
            'invitation_limit' => $validated['invitation_limit'],
        ]);

        $newLimit = $validated['invitation_limit'];

        return redirect()->back()->with('success', "Invitation limit updated from {$oldLimit} to {$newLimit}.");
    }

    /**
     * Reset user invitation limit to default (karma-based).
     */
    public function resetInvitationLimit(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $user->update([
            'invitation_limit' => null,
        ]);

        $karmaBasedLimit = $user->getInvitationLimit();

        return redirect()->back()->with('success', "Invitation limit reset to default (karma-based: {$karmaBasedLimit}).");
    }

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

    /**
     * Assign a role to a user.
     */
    public function assignRole(Request $request, User $user)
    {
        $this->authorize('admin-only');

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = \App\Models\Role::find($validated['role_id']);

        if ($user->roles()->where('role_id', $role->id)->exists()) {
            return redirect()->back()->with('error', "User already has the {$role->name} role.");
        }

        $user->roles()->attach($role->id);

        return redirect()->back()->with('success', "Role '{$role->name}' assigned successfully.");
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(Request $request, User $user)
    {
        $this->authorize('admin-only');

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = \App\Models\Role::find($validated['role_id']);

        if (! $user->roles()->where('role_id', $role->id)->exists()) {
            return redirect()->back()->with('error', "User doesn't have the {$role->name} role.");
        }

        $user->roles()->detach($role->id);

        return redirect()->back()->with('success', "Role '{$role->name}' removed successfully.");
    }

    /**
     * Toggle a special permission for a user.
     */
    public function togglePermission(Request $request, User $user): RedirectResponse
    {
        $this->authorize('admin-only');

        $validated = $request->validate([
            'permission' => 'required|in:can_create_subs',
        ]);

        $permission = $validated['permission'];
        $newValue = ! $user->$permission;
        $user->$permission = $newValue;
        $user->save();

        $permissionLabel = match ($permission) {
            'can_create_subs' => 'Create Communities',
            default => $permission,
        };

        $status = $newValue ? 'enabled' : 'disabled';

        return redirect()->back()->with('success', "Permission '{$permissionLabel}' {$status} for {$user->username}.");
    }

    /**
     * Show legal reports (DMCA, abuse, etc.).
     */
    public function legalReports(Request $request)
    {
        $this->authorize('access-admin');

        $query = LegalReport::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        // Search by reference number
        if ($request->filled('reference')) {
            $query->where('reference_number', 'like', '%' . $request->get('reference') . '%');
        }

        $reports = $query->latest()->paginate(50);

        return view('admin.legal-reports.index', compact('reports'));
    }

    /**
     * Show a specific legal report.
     */
    public function viewLegalReport(LegalReport $legalReport)
    {
        $this->authorize('access-admin');

        // Load notes with user relationship, notification sender, and all notifications
        $legalReport->load(['notes.user', 'notificationSender', 'notifications.sender']);

        return view('admin.legal-reports.view', compact('legalReport'));
    }

    /**
     * Update legal report status.
     */
    public function updateLegalReportStatus(Request $request, LegalReport $legalReport)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'status' => 'required|in:pending,under_review,resolved,rejected',
            'user_response' => 'nullable|string|max:5000',
        ]);

        $updateData = [
            'status' => $validated['status'],
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ];

        // If user_response is provided, save it and mark when it was sent
        if ($request->filled('user_response')) {
            $updateData['user_response'] = $validated['user_response'];
            $updateData['response_sent_at'] = now();
        }

        $legalReport->update($updateData);

        return redirect()->back()->with('success', 'Legal report status updated successfully.');
    }

    /**
     * Add an internal note to a legal report.
     */
    public function addLegalReportNote(Request $request, LegalReport $legalReport)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'note' => 'required|string|max:5000',
        ]);

        LegalReportNote::create([
            'legal_report_id' => $legalReport->id,
            'user_id' => Auth::id(),
            'note' => $validated['note'],
        ]);

        return redirect()->back()->with('success', 'Internal note added successfully.');
    }

    /**
     * Send notification email to the reporter about the resolution.
     */
    public function notifyLegalReportResolution(Request $request, LegalReport $legalReport)
    {
        $this->authorize('access-admin');

        // Verify there's a response and the status is resolved or rejected
        if (! $legalReport->user_response || ! in_array($legalReport->status, ['resolved', 'rejected'])) {
            return redirect()->back()->with('error', 'Cannot send notification: report must have a response and be resolved or rejected.');
        }

        // Validate the selected locale
        $validated = $request->validate([
            'locale' => 'required|in:en,es',
        ]);

        // Create a notification record with "sending" status
        $notification = \App\Models\LegalReportNotification::create([
            'legal_report_id' => $legalReport->id,
            'sent_by' => Auth::id(),
            'locale' => $validated['locale'],
            'content' => $legalReport->user_response,
            'status' => 'sending',
            'recipient_email' => $legalReport->reporter_email,
        ]);

        // Update legal_reports fields to maintain last notification info
        $sendingData = [
            'notification_sent_at' => now(),
            'notification_sent_by' => Auth::id(),
            'notification_locale' => $validated['locale'],
            'notification_content' => $legalReport->user_response,
            'notification_status' => 'sending',
            'notification_error' => null,
        ];

        // Also update response_sent_at if not already set
        if (! $legalReport->response_sent_at) {
            $sendingData['response_sent_at'] = now();
        }

        $legalReport->update($sendingData);

        try {
            // Send the email with the selected locale
            Mail::to($legalReport->reporter_email)->send(new \App\Mail\LegalReportResolutionMail($legalReport, $validated['locale']));

            // Mark notification as sent successfully
            $notification->update(['status' => 'sent']);

            // Update legal_report as well
            $legalReport->update([
                'notification_status' => 'sent',
            ]);

            $languageName = $validated['locale'] === 'es' ? 'Spanish' : 'English';

            return redirect()->back()->with('success', 'Notification email sent successfully to ' . $legalReport->reporter_email . ' in ' . $languageName);
        } catch (Exception $e) {
            Log::error('Error sending legal report notification: ' . $e->getMessage());

            // Mark notification as failed
            $notification->update([
                'status' => 'failed',
                'error_message' => ErrorHelper::getSafeError($e),
            ]);

            // Update legal_report as well
            $legalReport->update([
                'notification_status' => 'failed',
                'notification_error' => ErrorHelper::getSafeError($e),
            ]);

            return redirect()->back()->with('error', __('messages.admin.notification_error'));
        }
    }

    /**
     * Show karma history audit log.
     */
    public function karmaHistory(Request $request)
    {
        $this->authorize('access-admin');

        $query = DB::table('karma_histories')
            ->leftJoin('users', 'karma_histories.user_id', '=', 'users.id')
            ->select(
                'karma_histories.*',
                'users.username',
                'users.email',
            )
            ->orderBy('karma_histories.created_at', 'desc');

        // Filter by user
        $userKarma = null;
        if ($request->filled('user')) {
            $query->where('users.username', 'like', '%' . $request->user . '%');

            // Get user karma summary if filtering by single user
            $user = DB::table('users')
                ->where('username', 'like', '%' . $request->user . '%')
                ->first();

            if ($user) {
                $userKarma = [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'current_karma' => $user->karma ?? 0,
                ];
            }
        }

        // Filter by amount type (positive/negative)
        if ($request->filled('type')) {
            if ($request->type === 'positive') {
                $query->where('karma_histories.amount', '>', 0);
            } elseif ($request->type === 'negative') {
                $query->where('karma_histories.amount', '<', 0);
            }
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('karma_histories.created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('karma_histories.created_at', '<=', $request->to);
        }

        $history = $query->paginate(50);

        // Statistics
        $stats = [
            'total_transactions' => DB::table('karma_histories')->count(),
            'total_positive' => DB::table('karma_histories')->where('amount', '>', 0)->sum('amount'),
            'total_negative' => DB::table('karma_histories')->where('amount', '<', 0)->sum('amount'),
            'unique_users' => DB::table('karma_histories')->distinct('user_id')->count(),
        ];

        return view('admin.karma-history', compact('history', 'stats', 'userKarma'));
    }

    /**
     * Assign achievement to a user (admin only).
     */
    public function assignAchievement(Request $request, User $user)
    {
        $this->authorize('admin-only');

        $validated = $request->validate([
            'achievement_id' => 'required|exists:achievements,id',
        ]);

        $achievement = \App\Models\Achievement::findOrFail($validated['achievement_id']);

        // Check if user already has this achievement
        if ($user->achievements()->where('achievement_id', $achievement->id)->exists()) {
            return redirect()
                ->route('admin.users.show', $user)
                ->with('error', 'User already has this achievement.');
        }

        // Assign the achievement
        $user->achievements()->attach($achievement->id, [
            'unlocked_at' => now(),
            'progress' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Grant karma bonus
        if ($achievement->karma_bonus > 0) {
            $user->increment('karma_points', $achievement->karma_bonus);

            // Log karma change
            \App\Models\KarmaHistory::create([
                'user_id' => $user->id,
                'amount' => $achievement->karma_bonus,
                'source' => 'achievement',
                'source_id' => $achievement->id,
                'description' => "Achievement earned: {$achievement->name}",
            ]);
        }

        // Send notification
        $user->notify(new \App\Notifications\AchievementUnlocked($achievement));

        // Log admin action
        ModerationLog::create([
            'moderator_id' => auth()->id(),
            'action' => 'assign_achievement',
            'target_type' => 'user',
            'target_user_id' => $user->id,
            'details' => "Manually assigned achievement: {$achievement->name}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', "Achievement '{$achievement->name}' assigned successfully!");
    }

    /**
     * Remove achievement from a user (admin only).
     */
    public function removeAchievement(User $user, \App\Models\Achievement $achievement)
    {
        $this->authorize('admin-only');

        // Check if user has this achievement
        if (! $user->achievements()->where('achievement_id', $achievement->id)->exists()) {
            return redirect()
                ->route('admin.users.show', $user)
                ->with('error', 'User does not have this achievement.');
        }

        // Remove the achievement
        $user->achievements()->detach($achievement->id);

        // Deduct karma bonus
        if ($achievement->karma_bonus > 0) {
            $user->decrement('karma_points', $achievement->karma_bonus);

            // Log karma change
            \App\Models\KarmaHistory::create([
                'user_id' => $user->id,
                'amount' => -$achievement->karma_bonus,
                'source' => 'achievement',
                'source_id' => $achievement->id,
                'description' => "Achievement removed: {$achievement->name}",
            ]);
        }

        // Log admin action
        ModerationLog::create([
            'moderator_id' => auth()->id(),
            'action' => 'remove_achievement',
            'target_type' => 'user',
            'target_user_id' => $user->id,
            'details' => "Manually removed achievement: {$achievement->name}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', "Achievement '{$achievement->name}' removed successfully!");
    }

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
