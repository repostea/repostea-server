<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use const JSON_INVALID_UTF8_SUBSTITUTE;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentCollection;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * API controller for comment list/discovery operations.
 * Core CRUD and voting operations are in CommentController.
 */
final class CommentListController extends Controller
{
    private const COMMENTS_CACHE_TTL = 300; // 5 minutes

    /**
     * Get all comments with filtering and pagination.
     */
    public function getAll(Request $request): CommentCollection
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
                ->where('status', Comment::STATUS_PUBLISHED)
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

    /**
     * Get recent comments for sidebar.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 10), 20);
        $filter = $request->input('filter', 'recent'); // recent, top, funny, interesting, didactic, elaborate
        $days = min((int) $request->input('days', 3), 30);

        $query = Comment::with([
            'user' => fn ($query) => $query->withTrashed(),
            'post:id,slug,title',
        ])
            ->where('status', Comment::STATUS_PUBLISHED)
            ->where('is_anonymous', false)
            ->whereNull('remote_user_id') // Exclude federated comments
            ->whereHas('post', fn ($q) => $q->where('status', Post::STATUS_PUBLISHED));

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
    public function tops(Request $request): JsonResponse
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
                ->where('status', Comment::STATUS_PUBLISHED)
                ->where('is_anonymous', false)
                ->whereNull('remote_user_id') // Exclude federated comments
                ->whereHas('post', fn ($q) => $q->where('status', Post::STATUS_PUBLISHED));

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
