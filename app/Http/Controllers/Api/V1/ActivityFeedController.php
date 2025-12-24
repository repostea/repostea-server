<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgoraMessage;
use App\Models\AgoraVote;
use App\Models\Comment;
use App\Models\Post;
use App\Models\SealMark;
use App\Models\Sub;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ActivityFeedController extends Controller
{
    /**
     * Get the latest activities feed.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $limit = min($request->input('limit', 100), 500); // Max 500 items
        $offset = $request->input('offset', 0);
        $since = $request->input('since'); // ISO date string for filtering
        $types = $request->input('types'); // Comma-separated list of activity types

        // Parse activity types filter
        $selectedTypes = $types ? explode(',', $types) : [];
        $allTypes = ['new_post', 'post_vote', 'new_comment', 'comment_vote', 'seal_awarded', 'frontpage', 'new_agora_message', 'agora_vote', 'new_sub'];
        $activeTypes = ! empty($selectedTypes) ? array_intersect($selectedTypes, $allTypes) : $allTypes;

        // Build cache key
        $cacheKey = sprintf(
            'activity_feed:%s:%d:%d:%s',
            implode(',', $activeTypes),
            $limit,
            $offset,
            $since ?? 'all',
        );

        // Try to get from cache (5 minutes)
        $result = Cache::tags(['activity', 'posts'])->remember($cacheKey, 300, function () use ($limit, $offset, $since, $activeTypes) {
            $queries = [];

            // 1. New posts
            if (in_array('new_post', $activeTypes)) {
                $newPosts = Post::select(
                    DB::raw("'new_post' as activity_type"),
                    'id as activity_id',
                    'created_at',
                    'title',
                    'slug',
                    'user_id',
                    'is_anonymous',
                    DB::raw('NULL as post_id'),
                    DB::raw('NULL as post_title'),
                    DB::raw('NULL as post_slug'),
                    DB::raw('NULL as comment_content'),
                    DB::raw('NULL as comment_id'),
                    DB::raw('NULL as seal_name'),
                    DB::raw('NULL as vote_value'),
                    DB::raw('NULL as agora_message_id'),
                    DB::raw('NULL as agora_message_content'),
                    DB::raw('NULL as sub_name'),
                    DB::raw('NULL as sub_display_name'),
                );
                if ($since) {
                    $newPosts->where('created_at', '>=', $since);
                }
                $newPosts->orderBy('created_at', 'desc')->limit($limit);
                $queries[] = $newPosts;
            }

            // 2. New votes on posts (anonymous)
            if (in_array('post_vote', $activeTypes)) {
                $postVotes = Vote::select(
                    DB::raw("'post_vote' as activity_type"),
                    'votes.id as activity_id',
                    'votes.created_at',
                    DB::raw('NULL as title'),
                    DB::raw('NULL as slug'),
                    DB::raw('NULL as user_id'),
                    DB::raw('NULL as is_anonymous'),
                    'votes.votable_id as post_id',
                    'posts.title as post_title',
                    'posts.slug as post_slug',
                    DB::raw('NULL as comment_content'),
                    DB::raw('NULL as comment_id'),
                    DB::raw('NULL as seal_name'),
                    'votes.value as vote_value',
                    DB::raw('NULL as agora_message_id'),
                    DB::raw('NULL as agora_message_content'),
                    DB::raw('NULL as sub_name'),
                    DB::raw('NULL as sub_display_name'),
                )
                    ->join('posts', function ($join): void {
                        $join->on('votes.votable_id', '=', 'posts.id')
                            ->where('votes.votable_type', '=', 'App\\Models\\Post');
                    });
                if ($since) {
                    $postVotes->where('votes.created_at', '>=', $since);
                }
                $postVotes->orderBy('votes.created_at', 'desc')->limit($limit);
                $queries[] = $postVotes;
            }

            // 3. New comments
            if (in_array('new_comment', $activeTypes)) {
                $newComments = Comment::select(
                    DB::raw("'new_comment' as activity_type"),
                    'comments.id as activity_id',
                    'comments.created_at',
                    DB::raw('NULL as title'),
                    DB::raw('NULL as slug'),
                    'comments.user_id',
                    'comments.is_anonymous',
                    'comments.post_id',
                    'posts.title as post_title',
                    'posts.slug as post_slug',
                    'comments.content as comment_content',
                    'comments.id as comment_id',
                    DB::raw('NULL as seal_name'),
                    DB::raw('NULL as vote_value'),
                    DB::raw('NULL as agora_message_id'),
                    DB::raw('NULL as agora_message_content'),
                    DB::raw('NULL as sub_name'),
                    DB::raw('NULL as sub_display_name'),
                )
                    ->join('posts', 'comments.post_id', '=', 'posts.id');
                if ($since) {
                    $newComments->where('comments.created_at', '>=', $since);
                }
                $newComments->orderBy('comments.created_at', 'desc')->limit($limit);
                $queries[] = $newComments;
            }

            // 4. New votes on comments (anonymous)
            if (in_array('comment_vote', $activeTypes)) {
                $commentVotes = Vote::select(
                    DB::raw("'comment_vote' as activity_type"),
                    'votes.id as activity_id',
                    'votes.created_at',
                    DB::raw('NULL as title'),
                    DB::raw('NULL as slug'),
                    DB::raw('NULL as user_id'),
                    DB::raw('NULL as is_anonymous'),
                    'comments.post_id',
                    'posts.title as post_title',
                    'posts.slug as post_slug',
                    'comments.content as comment_content',
                    'comments.id as comment_id',
                    DB::raw('NULL as seal_name'),
                    'votes.value as vote_value',
                    DB::raw('NULL as agora_message_id'),
                    DB::raw('NULL as agora_message_content'),
                    DB::raw('NULL as sub_name'),
                    DB::raw('NULL as sub_display_name'),
                )
                    ->join('comments', function ($join): void {
                        $join->on('votes.votable_id', '=', 'comments.id')
                            ->where('votes.votable_type', '=', 'App\\Models\\Comment');
                    })
                    ->join('posts', 'comments.post_id', '=', 'posts.id');
                if ($since) {
                    $commentVotes->where('votes.created_at', '>=', $since);
                }
                $commentVotes->orderBy('votes.created_at', 'desc')->limit($limit);
                $queries[] = $commentVotes;
            }

            // 5. New seals awarded (anonymous)
            if (in_array('seal_awarded', $activeTypes)) {
                $newSeals = SealMark::select(
                    DB::raw("'seal_awarded' as activity_type"),
                    'seal_marks.id as activity_id',
                    'seal_marks.created_at',
                    DB::raw('NULL as title'),
                    DB::raw('NULL as slug'),
                    DB::raw('NULL as user_id'),
                    DB::raw('NULL as is_anonymous'),
                    DB::raw('CASE
                        WHEN seal_marks.markable_type = "App\\\\Models\\\\Post" THEN seal_marks.markable_id
                        WHEN seal_marks.markable_type = "App\\\\Models\\\\Comment" THEN comments.post_id
                        ELSE NULL
                    END as post_id'),
                    DB::raw('posts.title as post_title'),
                    DB::raw('posts.slug as post_slug'),
                    DB::raw('CASE
                        WHEN seal_marks.markable_type = "App\\\\Models\\\\Comment" THEN comments.content
                        ELSE NULL
                    END as comment_content'),
                    DB::raw('CASE
                        WHEN seal_marks.markable_type = "App\\\\Models\\\\Comment" THEN comments.id
                        ELSE NULL
                    END as comment_id'),
                    'seal_marks.type as seal_name',
                    DB::raw('NULL as vote_value'),
                    DB::raw('NULL as agora_message_id'),
                    DB::raw('NULL as agora_message_content'),
                    DB::raw('NULL as sub_name'),
                    DB::raw('NULL as sub_display_name'),
                )
                    ->leftJoin('comments', function ($join): void {
                        $join->on('seal_marks.markable_id', '=', 'comments.id')
                            ->where('seal_marks.markable_type', '=', 'App\\Models\\Comment');
                    })
                    ->leftJoin('posts', function ($join): void {
                        $join->on('seal_marks.markable_id', '=', 'posts.id')
                            ->where('seal_marks.markable_type', '=', 'App\\Models\\Post')
                            ->orWhereColumn('comments.post_id', 'posts.id');
                    });
                if ($since) {
                    $newSeals->where('seal_marks.created_at', '>=', $since);
                }
                $newSeals->orderBy('seal_marks.created_at', 'desc')->limit($limit);
                $queries[] = $newSeals;
            }

            // 6. Posts reaching frontpage
            if (in_array('frontpage', $activeTypes)) {
                $frontpagePosts = Post::select(
                    DB::raw("'frontpage' as activity_type"),
                    'id as activity_id',
                    'frontpage_at as created_at',
                    'title',
                    'slug',
                    'user_id',
                    'is_anonymous',
                    DB::raw('NULL as post_id'),
                    DB::raw('NULL as post_title'),
                    DB::raw('NULL as post_slug'),
                    DB::raw('NULL as comment_content'),
                    DB::raw('NULL as comment_id'),
                    DB::raw('NULL as seal_name'),
                    DB::raw('NULL as vote_value'),
                    DB::raw('NULL as agora_message_id'),
                    DB::raw('NULL as agora_message_content'),
                    DB::raw('NULL as sub_name'),
                    DB::raw('NULL as sub_display_name'),
                )
                    ->whereNotNull('frontpage_at');
                if ($since) {
                    $frontpagePosts->where('frontpage_at', '>=', $since);
                }
                $frontpagePosts->orderBy('frontpage_at', 'desc')->limit($limit);
                $queries[] = $frontpagePosts;
            }

            // 7. New Agora messages
            if (in_array('new_agora_message', $activeTypes)) {
                $newAgoraMessages = AgoraMessage::select(
                    DB::raw("'new_agora_message' as activity_type"),
                    'id as activity_id',
                    'created_at',
                    DB::raw('NULL as title'),
                    DB::raw('NULL as slug'),
                    'user_id',
                    'is_anonymous',
                    DB::raw('NULL as post_id'),
                    DB::raw('NULL as post_title'),
                    DB::raw('NULL as post_slug'),
                    DB::raw('NULL as comment_content'),
                    DB::raw('NULL as comment_id'),
                    DB::raw('NULL as seal_name'),
                    DB::raw('NULL as vote_value'),
                    'id as agora_message_id',
                    'content as agora_message_content',
                    DB::raw('NULL as sub_name'),
                    DB::raw('NULL as sub_display_name'),
                );
                if ($since) {
                    $newAgoraMessages->where('created_at', '>=', $since);
                }
                $newAgoraMessages->orderBy('created_at', 'desc')->limit($limit);
                $queries[] = $newAgoraMessages;
            }

            // 8. Agora votes
            if (in_array('agora_vote', $activeTypes)) {
                $agoraVotes = AgoraVote::select(
                    DB::raw("'agora_vote' as activity_type"),
                    'agora_votes.id as activity_id',
                    'agora_votes.created_at',
                    DB::raw('NULL as title'),
                    DB::raw('NULL as slug'),
                    DB::raw('NULL as user_id'),
                    DB::raw('NULL as is_anonymous'),
                    DB::raw('NULL as post_id'),
                    DB::raw('NULL as post_title'),
                    DB::raw('NULL as post_slug'),
                    DB::raw('NULL as comment_content'),
                    DB::raw('NULL as comment_id'),
                    DB::raw('NULL as seal_name'),
                    'agora_votes.value as vote_value',
                    'agora_votes.agora_message_id',
                    'agora_messages.content as agora_message_content',
                    DB::raw('NULL as sub_name'),
                    DB::raw('NULL as sub_display_name'),
                )
                    ->join('agora_messages', 'agora_votes.agora_message_id', '=', 'agora_messages.id');
                if ($since) {
                    $agoraVotes->where('agora_votes.created_at', '>=', $since);
                }
                $agoraVotes->orderBy('agora_votes.created_at', 'desc')->limit($limit);
                $queries[] = $agoraVotes;
            }

            // 9. New subs created
            if (in_array('new_sub', $activeTypes)) {
                $newSubs = Sub::select(
                    DB::raw("'new_sub' as activity_type"),
                    'id as activity_id',
                    'created_at',
                    DB::raw('NULL as title'),
                    DB::raw('NULL as slug'),
                    'created_by as user_id',
                    DB::raw('0 as is_anonymous'),
                    DB::raw('NULL as post_id'),
                    DB::raw('NULL as post_title'),
                    DB::raw('NULL as post_slug'),
                    DB::raw('NULL as comment_content'),
                    DB::raw('NULL as comment_id'),
                    DB::raw('NULL as seal_name'),
                    DB::raw('NULL as vote_value'),
                    DB::raw('NULL as agora_message_id'),
                    DB::raw('NULL as agora_message_content'),
                    'name as sub_name',
                    'display_name as sub_display_name',
                );
                if ($since) {
                    $newSubs->where('created_at', '>=', $since);
                }
                $newSubs->orderBy('created_at', 'desc')->limit($limit);
                $queries[] = $newSubs;
            }

            // If no queries, return empty
            if (empty($queries)) {
                return [
                    'activities' => [],
                    'total' => 0,
                    'limit' => $limit,
                    'offset' => $offset,
                ];
            }

            // Combine all queries using union
            $query = $queries[0];
            for ($i = 1; $i < count($queries); $i++) {
                $query = $query->union($queries[$i]);
            }

            // Execute and sort by date
            $allActivities = DB::table(DB::raw("({$query->toSql()}) as activities"))
                ->mergeBindings($query->getQuery())
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return $allActivities;
        });

        // Load user data for activities that have user_id
        $userIds = $result->whereNotNull('user_id')->pluck('user_id')->unique();
        $users = \App\Models\User::whereIn('id', $userIds)
            ->select('id', 'username', 'avatar')
            ->get()
            ->keyBy('id');

        // Format activities with user data
        $formattedActivities = $result->map(function ($activity) use ($users) {
            $data = (array) $activity;

            // Add user data if available and not anonymous
            // Cast is_anonymous to bool to handle SQLite integer values (0/1)
            $isAnonymous = (bool) $activity->is_anonymous;
            if ($activity->user_id && ! $isAnonymous) {
                $user = $users->get($activity->user_id);
                $data['user'] = $user ? [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ] : null;
            } else {
                $data['user'] = null;
            }

            // Remove internal fields
            unset($data['user_id'], $data['is_anonymous']);

            return $data;
        });

        return response()->json([
            'activities' => $formattedActivities,
            'total' => $formattedActivities->count(),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
