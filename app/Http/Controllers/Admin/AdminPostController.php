<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\PostView;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin controller for post and comment management.
 */
final class AdminPostController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all posts with moderation filters.
     */
    public function index(Request $request)
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

        // Sorting - use database-level ordering for efficiency
        $sortBy = $request->get('sort', 'created_at');
        $allowedSorts = ['created_at', 'updated_at', 'views', 'total_visitors', 'identified_visitors'];
        if (! in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        $direction = $request->get('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $direction);

        // Paginate at database level (much more efficient than loading all)
        $posts = $query->paginate(50)->withQueryString();

        return view('admin.posts.index', compact('posts'));
    }

    /**
     * View post details.
     */
    public function show(Post $post)
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
        $userAgents = PostView::select(
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
    public function hide(Request $request, Post $post)
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
    public function restore(Request $request, Post $post)
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
    public function approve(Request $request, Post $post)
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
    public function destroy(Request $request, Post $post)
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

    // =========================================================================
    // Comment Management
    // =========================================================================

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
    public function destroyComment(Request $request, Comment $comment)
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
}
