<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use const JSON_INVALID_UTF8_SUBSTITUTE;

use App\Events\NewCommentPosted;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentCollection;
use App\Http\Resources\CommentResource;
use App\Http\Resources\VoteStatsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentReplied;
use App\Notifications\PostCommented;
use App\Notifications\UserMentioned;
use App\Services\CommentModerationService;
use App\Services\CommentVoteService;
use App\Services\RealtimeBroadcastService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CommentController extends Controller
{
    private const COMMENTS_CACHE_TTL = 300; // 5 minutes

    public function __construct(
        protected CommentVoteService $voteService,
        protected RealtimeBroadcastService $realtimeService,
        protected CommentModerationService $moderationService,
    ) {}

    public function index(Post $post)
    {
        // Load ALL comments for this post at once (flat structure)
        $allComments = $post->comments()
            ->whereIn('status', ['published', 'hidden', 'deleted_by_author'])
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

    public function store(Request $request, Post $post)
    {
        $request->validate([
            'content' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:comments,id',
            'is_anonymous' => 'nullable|boolean',
        ]);

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
            DB::table('post_views')
                ->where('post_id', $post->id)
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
            $parentComment = Comment::find($comment->parent_id);
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

    public function update(Request $request, Comment $comment)
    {
        Gate::authorize('update', $comment);

        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $comment->update([
            'content' => $request->input('content'),
            'edited_at' => now(),
        ]);

        return new CommentResource($comment);
    }

    public function destroy(Comment $comment)
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
    public function moderate(Request $request, Comment $comment)
    {
        Gate::authorize('moderate', $comment);

        $request->validate([
            'action' => 'required|in:hide,unhide,delete,restore',
            'reason' => 'nullable|string|max:500',
        ]);

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

    public function vote(Request $request, Comment $comment)
    {
        $request->validate([
            'value' => 'required|integer|in:1,-1',
            'type' => 'nullable|string',
        ]);

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

    public function voteStats(Comment $comment)
    {
        $comment->load(['votes.user' => fn ($query) => $query->withTrashed()]);
        $statsData = (new VoteStatsResource($comment))->resolve(request());

        return response()->json($statsData);
    }

    public function unvote(Comment $comment)
    {
        $result = $this->voteService->unvoteComment($comment);

        $comment->load(['votes.user' => fn ($query) => $query->withTrashed()]);

        return response()->json([
            'message' => $result['message'],
            'stats' => new VoteStatsResource($comment),
        ]);
    }

    /**
     * Get recent comments for sidebar.
     */
    public function getAll(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 20), 50);
        $sortBy = $request->input('sort_by', 'recent');
        $timeInterval = min((int) $request->input('time_interval', 0), 43200); // Max 30 days in minutes
        $sub = $request->input('sub', '');
        $page = (int) $request->input('page', 1);

        // Build cache key from request parameters
        $cacheKey = sprintf(
            'comments:list:%s:%s:%d:%s:%d',
            $sortBy,
            $sub,
            $timeInterval,
            $perPage,
            $page,
        );

        $comments = Cache::remember($cacheKey, self::COMMENTS_CACHE_TTL, function () use ($perPage, $sortBy, $timeInterval, $sub) {
            $query = Comment::with([
                'user' => fn ($query) => $query->withTrashed(),
                'post:id,slug,title,sub_id',
                'post.sub:id,name',
                'votes',
            ])
                ->where('status', 'published')
                ->where('is_anonymous', false);

            // Filter by sub if provided
            if ($sub) {
                $query->whereHas('post.sub', fn ($q) => $q->where('name', $sub));
            }

            // Apply time interval filter (except for 'recent')
            if ($timeInterval > 0 && $sortBy !== 'recent') {
                $query->where('created_at', '>=', now()->subMinutes($timeInterval));
            }

            // Apply sorting
            switch ($sortBy) {
                case 'recent':
                    $query->orderBy('created_at', 'desc');

                    break;
                case 'votes':
                    // Most voted (positive - negative) - minimum 2 positive votes
                    $query->withCount([
                        'votes as vote_count' => function ($q): void {
                            $q->select(DB::raw('COALESCE(SUM(value), 0)'));
                        },
                        'votes as positive_votes' => fn ($q) => $q->where('value', 1),
                    ])
                        ->orderBy('vote_count', 'desc')
                        ->having('positive_votes', '>=', 2); // Minimum 2 positive votes

                    break;
                case 'didactic':
                case 'interesting':
                case 'elaborate':
                case 'funny':
                    // Order by specific vote type (minimum 2 votes required)
                    $query->withCount([
                        'votes as type_vote_count' => fn ($q) => $q->where('type', $sortBy)->where('value', 1),
                    ])
                        ->orderBy('type_vote_count', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->having('type_vote_count', '>=', 2); // Minimum 2 votes

                    break;
            }

            return $query->paginate($perPage);
        });

        // Add vote type to each comment if sorted by vote type
        if (in_array($sortBy, ['didactic', 'interesting', 'elaborate', 'funny'])) {
            $comments->getCollection()->transform(function ($comment) use ($sortBy) {
                $comment->vote_type = $sortBy;

                return $comment;
            });
        }

        return new CommentCollection($comments);
    }

    public function recent(Request $request)
    {
        $limit = min((int) $request->input('limit', 10), 20);
        $filter = $request->input('filter', 'recent'); // recent, top, funny, interesting, didactic, elaborate
        $days = min((int) $request->input('days', 3), 30);

        $query = Comment::with([
            'user' => fn ($query) => $query->withTrashed(),
            'post:id,slug,title',
        ])
            ->where('status', 'published')
            ->where('is_anonymous', false)
            ->whereNull('remote_user_id'); // Exclude federated comments

        // Apply filter
        if ($filter === 'recent') {
            $query->orderBy('created_at', 'desc');
        } elseif ($filter === 'top') {
            // Most voted (positive - negative) in last N days (minimum 2 votes)
            $query->where('created_at', '>=', now()->subDays($days))
                ->withCount([
                    'votes as positive_votes' => fn ($q) => $q->where('value', 1),
                    'votes as negative_votes' => fn ($q) => $q->where('value', -1),
                ])
                ->orderByRaw('(positive_votes - negative_votes) DESC')
                ->orderBy('created_at', 'desc')
                ->having('positive_votes', '>=', 2); // Minimum 2 positive votes
        } else {
            // Filter by vote type (funny, interesting, didactic, elaborate) - minimum 2 votes
            $voteTypeMap = [
                'funny' => 'funny',
                'interesting' => 'interesting',
                'didactic' => 'didactic',
                'elaborate' => 'elaborate',
            ];

            if (isset($voteTypeMap[$filter])) {
                $query->where('created_at', '>=', now()->subDays($days))
                    ->withCount([
                        'votes as type_votes' => fn ($q) => $q->where('type', $voteTypeMap[$filter]),
                    ])
                    ->orderBy('type_votes', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->having('type_votes', '>=', 2); // Minimum 2 votes of this type
            }
        }

        $includeAgora = $request->boolean('include_agora', false);

        $comments = $query->limit($limit)
            ->get()
            ->map(fn ($comment) => [
                'id' => $comment->id,
                'content' => substr(strip_tags($comment->content), 0, 150),
                'created_at' => $comment->created_at,
                'votes_count' => $comment->votes_count ?? 0,
                'positive_votes' => $comment->positive_votes ?? 0,
                'negative_votes' => $comment->negative_votes ?? 0,
                'type_votes' => $comment->type_votes ?? 0,
                'is_agora' => false,
                'user' => [
                    'username' => $comment->user->username ?? '[deleted]',
                    'display_name' => $comment->user->display_name ?? '[deleted]',
                    'avatar' => $comment->user->avatar ?? null,
                ],
                'post' => [
                    'id' => $comment->post->id,
                    'slug' => $comment->post->slug,
                    'title' => $comment->post->title,
                ],
            ]);

        // Include Agora messages if requested
        if ($includeAgora && $filter === 'recent') {
            $agoraMessages = \App\Models\AgoraMessage::with(['user' => fn ($q) => $q->withTrashed()])
                ->whereNull('parent_id')
                ->where('expires_at', '>', now())
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($msg) => [
                    'id' => $msg->id,
                    'content' => substr(strip_tags($msg->content), 0, 150),
                    'created_at' => $msg->created_at,
                    'votes_count' => 0,
                    'positive_votes' => 0,
                    'negative_votes' => 0,
                    'type_votes' => 0,
                    'is_agora' => true,
                    'user' => [
                        'username' => $msg->user->username ?? '[deleted]',
                        'display_name' => $msg->user->display_name ?? '[deleted]',
                        'avatar' => $msg->user->avatar ?? null,
                    ],
                    'post' => null,
                ]);

            // Merge and sort by created_at
            $merged = $comments->concat($agoraMessages)
                ->sortByDesc('created_at')
                ->take($limit)
                ->values();

            return response()->json([
                'data' => $merged,
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        return response()->json([
            'data' => $comments,
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Get top comments with intelligent fallback.
     * If no results found in initial days, automatically expands to 7, 14, 30 days until minimum results found.
     */
    public function tops(Request $request)
    {
        $limit = min((int) $request->input('limit', 5), 20);
        $filter = $request->input('filter', 'top'); // top, funny, interesting, didactic, elaborate
        $initialDays = min((int) $request->input('days', 3), 30);
        $locale = $request->input('locale', 'es');

        // Fallback intervals: try initial days, then 7, 14, 30 if not enough results
        $dayIntervals = [$initialDays, 7, 14, 30];
        $dayIntervals = array_unique($dayIntervals);
        sort($dayIntervals);

        $comments = collect();
        $usedDays = $initialDays;

        // Try each interval until we get enough comments
        foreach ($dayIntervals as $days) {
            $query = Comment::with([
                'user' => fn ($query) => $query->withTrashed(),
                'post:id,slug,title',
            ])
                ->where('status', 'published')
                ->where('is_anonymous', false)
                ->whereNull('remote_user_id'); // Exclude federated comments

            // Apply filter
            if ($filter === 'top') {
                // Most voted (positive - negative) in last N days (minimum 2 votes)
                $query->where('created_at', '>=', now()->subDays($days))
                    ->withCount([
                        'votes as positive_votes' => fn ($q) => $q->where('value', 1),
                        'votes as negative_votes' => fn ($q) => $q->where('value', -1),
                    ])
                    ->orderByRaw('(positive_votes - negative_votes) DESC')
                    ->orderBy('created_at', 'desc')
                    ->having('positive_votes', '>=', 2); // Minimum 2 positive votes
            } else {
                // Filter by vote type (funny, interesting, didactic, elaborate) - minimum 2 votes
                $voteTypeMap = [
                    'funny' => 'funny',
                    'interesting' => 'interesting',
                    'didactic' => 'didactic',
                    'elaborate' => 'elaborate',
                ];

                if (isset($voteTypeMap[$filter])) {
                    $query->where('created_at', '>=', now()->subDays($days))
                        ->withCount([
                            'votes as type_votes' => fn ($q) => $q->where('type', $voteTypeMap[$filter])->where('value', 1),
                        ])
                        ->orderBy('type_votes', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->having('type_votes', '>=', 2); // Minimum 2 votes of this type
                }
            }

            $comments = $query->limit($limit)->get();
            $usedDays = $days;

            // If we found enough comments, stop trying
            if ($comments->count() >= $limit || $comments->count() >= 3) {
                break;
            }
        }

        $includeAgora = $request->boolean('include_agora', false);

        $formattedComments = $comments->map(fn ($comment) => [
            'id' => $comment->id,
            'content' => substr(strip_tags($comment->content), 0, 150),
            'created_at' => $comment->created_at,
            'votes_count' => $comment->votes_count ?? 0,
            'positive_votes' => $comment->positive_votes ?? 0,
            'negative_votes' => $comment->negative_votes ?? 0,
            'type_votes' => $comment->type_votes ?? 0,
            'is_agora' => false,
            'user' => [
                'username' => $comment->user->username ?? '[deleted]',
                'display_name' => $comment->user->display_name ?? '[deleted]',
                'avatar' => $comment->user->avatar ?? null,
            ],
            'post' => [
                'id' => $comment->post->id,
                'slug' => $comment->post->slug,
                'title' => $comment->post->title,
            ],
        ]);

        // Include Agora messages if requested
        if ($includeAgora) {
            $agoraQuery = \App\Models\AgoraMessage::with(['user' => fn ($q) => $q->withTrashed()])
                ->whereNull('parent_id')
                ->where('expires_at', '>', now())
                ->where('created_at', '>=', now()->subDays($usedDays));

            if ($filter === 'top') {
                $agoraQuery->withCount([
                    'votes as positive_votes' => fn ($q) => $q->where('value', 1),
                    'votes as negative_votes' => fn ($q) => $q->where('value', -1),
                ])
                    ->orderByRaw('(positive_votes - negative_votes) DESC')
                    ->having('positive_votes', '>=', 2);
            } else {
                $voteTypeMap = [
                    'funny' => 'funny',
                    'interesting' => 'interesting',
                    'didactic' => 'didactic',
                    'elaborate' => 'elaborate',
                ];

                if (isset($voteTypeMap[$filter])) {
                    $agoraQuery->withCount([
                        'votes as type_votes' => fn ($q) => $q->where('vote_type', $voteTypeMap[$filter])->where('value', 1),
                    ])
                        ->orderBy('type_votes', 'desc')
                        ->having('type_votes', '>=', 2);
                }
            }

            $agoraMessages = $agoraQuery->limit($limit)
                ->get()
                ->map(fn ($msg) => [
                    'id' => $msg->id,
                    'content' => substr(strip_tags($msg->content), 0, 150),
                    'created_at' => $msg->created_at,
                    'votes_count' => ($msg->positive_votes ?? 0) - ($msg->negative_votes ?? 0),
                    'positive_votes' => $msg->positive_votes ?? 0,
                    'negative_votes' => $msg->negative_votes ?? 0,
                    'type_votes' => $msg->type_votes ?? 0,
                    'is_agora' => true,
                    'user' => [
                        'username' => $msg->user->username ?? '[deleted]',
                        'display_name' => $msg->user->display_name ?? '[deleted]',
                        'avatar' => $msg->user->avatar ?? null,
                    ],
                    'post' => null,
                ]);

            // Merge and sort by vote count
            $formattedComments = $formattedComments->concat($agoraMessages)
                ->sortByDesc(fn ($item) => $filter === 'top'
                    ? ($item['positive_votes'] - $item['negative_votes'])
                    : $item['type_votes'],
                )
                ->take($limit)
                ->values();
        }

        return response()->json([
            'data' => $formattedComments,
            'meta' => [
                'requested_days' => $initialDays,
                'used_days' => $usedDays,
                'fallback_applied' => $usedDays !== $initialDays,
            ],
        ]);
    }
}
