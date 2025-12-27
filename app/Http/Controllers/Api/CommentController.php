<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\NewCommentPosted;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModerateCommentRequest;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Requests\VoteCommentRequest;
use App\Http\Resources\CommentCollection;
use App\Http\Resources\CommentResource;
use App\Http\Resources\VoteStatsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use App\Notifications\CommentReplied;
use App\Notifications\PostCommented;
use App\Notifications\UserMentioned;
use App\Services\CommentModerationService;
use App\Services\CommentVoteService;
use App\Services\RealtimeBroadcastService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * API controller for comment CRUD and voting operations.
 * List/discovery operations are in CommentListController.
 */
final class CommentController extends Controller
{
    public function __construct(
        protected CommentVoteService $voteService,
        protected RealtimeBroadcastService $realtimeService,
        protected CommentModerationService $moderationService,
    ) {}

    public function index(Post $post): CommentCollection
    {
        // Load ALL comments for this post at once (flat structure)
        $allComments = $post->comments()
            ->whereIn('status', [Comment::STATUS_PUBLISHED, Comment::STATUS_HIDDEN, Comment::STATUS_DELETED_BY_AUTHOR])
            ->orderBy('created_at', 'asc')
            ->with([
                'user' => fn ($query) => $query->withTrashed(),
                'remoteUser',
                'moderatedBy',
                'votes.user' => fn ($query) => $query->withTrashed(),
            ])
            ->get();

        // Build the tree structure recursively (unlimited levels)
        $commentsById = $allComments->keyBy('id');

        // Initialize replies collection for each comment
        foreach ($allComments as $comment) {
            $comment->setRelation('replies', collect());
        }

        // Build parent-child relationships
        foreach ($allComments as $comment) {
            if ($comment->parent_id && isset($commentsById[$comment->parent_id])) {
                $commentsById[$comment->parent_id]->replies->push($comment);
            }
        }

        // Get only root comments (no parent)
        $comments = $allComments->filter(fn ($comment) => $comment->parent_id === null)->values();

        $this->addUserVoteInfo($comments);
        $this->addUserSealInfo($comments);

        return new CommentCollection($comments);
    }

    private function addUserVoteInfo($comments): void
    {
        if (! Auth::check()) {
            return;
        }

        $userId = Auth::id();

        // Optimized: Collect all comment IDs (including nested replies)
        $commentIds = $this->collectCommentIds($comments);

        // Single query to get all user votes for these comments
        $userVotes = \App\Models\Vote::where('user_id', $userId)
            ->where('votable_type', Comment::class)
            ->whereIn('votable_id', $commentIds)
            ->get()
            ->keyBy('votable_id');

        // Attach vote info to each comment (no additional queries)
        $this->attachVotesToComments($comments, $userVotes);
    }

    private function attachVotesToComments($comments, $userVotes): void
    {
        foreach ($comments as $comment) {
            $userVote = $userVotes->get($comment->id);
            $comment->user_vote = $userVote?->value;
            $comment->user_vote_type = $userVote?->type;

            if ($comment->replies && $comment->replies->count() > 0) {
                $this->attachVotesToComments($comment->replies, $userVotes);
            }
        }
    }

    private function addUserSealInfo($comments): void
    {
        if (! Auth::check()) {
            // For anonymous users, set all to false
            foreach ($comments as $comment) {
                $comment->user_has_recommended = false;
                $comment->user_has_advise_against = false;
                $comment->recommended_seals_count = $comment->recommended_seals_count ?? 0;
                $comment->advise_against_seals_count = $comment->advise_against_seals_count ?? 0;

                if ($comment->replies && $comment->replies->count() > 0) {
                    $this->addUserSealInfo($comment->replies);
                }
            }

            return;
        }

        $userId = Auth::id();

        // Collect all comment IDs (including nested replies)
        $commentIds = $this->collectCommentIds($comments);

        // Load all seal marks for these comments in one query
        $sealMarks = \App\Models\SealMark::where('user_id', $userId)
            ->where('markable_type', Comment::class)
            ->whereIn('markable_id', $commentIds)
            ->where('expires_at', '>', now())
            ->get()
            ->groupBy('markable_id');

        // Attach seal info to each comment
        $this->attachSealInfoToComments($comments, $sealMarks);
    }

    private function collectCommentIds($comments): array
    {
        $ids = [];
        foreach ($comments as $comment) {
            $ids[] = $comment->id;
            if ($comment->replies && $comment->replies->count() > 0) {
                $ids = array_merge($ids, $this->collectCommentIds($comment->replies));
            }
        }

        return $ids;
    }

    private function attachSealInfoToComments($comments, $sealMarks): void
    {
        foreach ($comments as $comment) {
            $commentMarks = $sealMarks->get($comment->id);

            $comment->user_has_recommended = $commentMarks !== null && $commentMarks->contains('type', 'recommended');
            $comment->user_has_advise_against = $commentMarks !== null && $commentMarks->contains('type', 'advise_against');
            $comment->recommended_seals_count = $comment->recommended_seals_count ?? 0;
            $comment->advise_against_seals_count = $comment->advise_against_seals_count ?? 0;

            if ($comment->replies && $comment->replies->count() > 0) {
                $this->attachSealInfoToComments($comment->replies, $sealMarks);
            }
        }
    }

    public function store(StoreCommentRequest $request, Post $post): CommentResource|JsonResponse
    {
        // Check if post is too old for commenting (0 = always open)
        $maxAgeDays = (int) config('posts.commenting_max_age_days', 0);
        if ($maxAgeDays > 0 && (int) $post->created_at->diffInDays(now()) > $maxAgeDays) {
            return response()->json([
                'message' => __('messages.comments.too_old'),
            ], 403);
        }

        DB::beginTransaction();
        try {
            $comment = $post->comments()->create([
                'content' => $request->input('content'),
                'user_id' => Auth::id(),
                'parent_id' => $request->parent_id,
                'is_anonymous' => $request->input('is_anonymous', false),
            ]);

            // Increment comment count for all comments (including nested replies)
            $post->increment('comment_count');

            // Queue realtime broadcast for comment count update
            $post->refresh();
            $this->realtimeService->queueCommentChange($post, 1);

            // Update last_visited_at when user comments on the post
            PostView::where('post_id', $post->id)
                ->where('user_id', Auth::id())
                ->update([
                    'last_visited_at' => now(),
                    'updated_at' => now(),
                ]);

            $comment->load(['user' => fn ($query) => $query->withTrashed()]);

            DB::commit();

            // Send notifications after successful commit
            $this->sendNotifications($comment, $post);

            // Broadcast new comment to users viewing this post
            broadcast(new NewCommentPosted($comment, $post->id))->toOthers();

            // Dispatch async duplicate content check
            \App\Jobs\CheckContentDuplicate::dispatch('comment', $comment->id, $comment->user_id);

            return new CommentResource($comment);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Send notifications for a new comment.
     */
    private function sendNotifications(Comment $comment, Post $post): void
    {
        $commenter = Auth::user();

        // Don't send notifications for anonymous comments
        if ($comment->is_anonymous) {
            return;
        }

        // 1. Notify post author (if it's a top-level comment and not their own post)
        if ($comment->parent_id === null && $post->user_id !== $commenter->id && $post->user) {
            $post->user->notify(new PostCommented($comment, $post, $commenter));
        }

        // 2. Notify parent comment author (if it's a reply)
        if ($comment->parent_id !== null) {
            $parentComment = Comment::with('user')->find($comment->parent_id);
            if ($parentComment && $parentComment->user_id !== $commenter->id && $parentComment->user) {
                $parentComment->user->notify(new CommentReplied($comment, $parentComment, $post, $commenter));
            }
        }

        // 3. Notify mentioned users (@username)
        $this->notifyMentionedUsers($comment, $post, $commenter);
    }

    /**
     * Extract and notify mentioned users.
     */
    private function notifyMentionedUsers(Comment $comment, Post $post, User $commenter): void
    {
        // Match @username pattern (alphanumeric and underscore)
        // Uses negative lookbehind to exclude @ preceded by / (inside URLs like mastodon.social/@user)
        preg_match_all('/(?<![\/\w])@([a-zA-Z0-9_]+)/', $comment->content, $matches);

        if (empty($matches[1])) {
            return;
        }

        $mentionedUsernames = array_unique($matches[1]);

        // Find users and send notifications
        $users = User::whereIn('username', $mentionedUsernames)
            ->where('id', '!=', $commenter->id) // Don't notify yourself
            ->get();

        foreach ($users as $user) {
            $user->notify(new UserMentioned($comment, $post, $commenter));
        }
    }

    public function update(UpdateCommentRequest $request, Comment $comment): CommentResource
    {
        Gate::authorize('update', $comment);

        $comment->update([
            'content' => $request->input('content'),
            'edited_at' => now(),
        ]);

        return new CommentResource($comment);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        DB::beginTransaction();
        try {
            // Soft delete: change status instead of deleting from database
            // This preserves the comment thread structure
            $comment->update([
                'status' => 'deleted_by_author',
                'content' => '[deleted]', // Clear content for privacy
            ]);

            DB::commit();

            return ApiResponse::success(null, __('messages.comments.deleted'));
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Moderate a comment (hide, unhide, delete by moderator).
     * Works for both local and remote comments.
     */
    public function moderate(ModerateCommentRequest $request, Comment $comment): JsonResponse
    {
        Gate::authorize('moderate', $comment);

        $action = $request->input('action');
        $reason = $request->input('reason');
        $moderator = $request->user();

        DB::beginTransaction();
        try {
            $result = $this->moderationService->moderate($comment, $action, $moderator, $reason);

            if (! $result['success']) {
                DB::rollBack();

                return ApiResponse::error($result['message'], null, 400);
            }

            DB::commit();

            return ApiResponse::success([
                'comment' => new CommentResource($result['comment']),
            ], $result['message']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function vote(VoteCommentRequest $request, Comment $comment): JsonResponse
    {
        $result = $this->voteService->voteComment(
            $comment,
            $request->value,
            $request->type,
        );

        if (! $result['success']) {
            return ApiResponse::error($result['message'], null, 422);
        }

        $comment->load(['votes.user' => fn ($query) => $query->withTrashed()]);

        return ApiResponse::success([
            'stats' => new VoteStatsResource($comment),
            'user_vote' => $result['user_vote'],
            'user_vote_type' => $result['user_vote_type'],
        ], $result['message']);
    }

    public function voteStats(Comment $comment): JsonResponse
    {
        $comment->load(['votes.user' => fn ($query) => $query->withTrashed()]);
        $statsData = (new VoteStatsResource($comment))->resolve(request());

        return response()->json($statsData);
    }

    public function unvote(Comment $comment): JsonResponse
    {
        $result = $this->voteService->unvoteComment($comment);

        $comment->load(['votes.user' => fn ($query) => $query->withTrashed()]);

        return response()->json([
            'message' => $result['message'],
            'stats' => new VoteStatsResource($comment),
        ]);
    }
}
