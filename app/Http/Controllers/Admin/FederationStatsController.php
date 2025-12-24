<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubBlockedInstance;
use App\Models\ActivityPubDeliveryLog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\RemoteUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class FederationStatsController extends Controller
{
    /**
     * Get federation statistics dashboard data.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'actors' => $this->getActorStats(),
            'followers' => $this->getFollowerStats(),
            'content' => $this->getContentStats(),
            'blocked_instances' => $this->getBlockedInstanceStats(),
            'deliveries' => ActivityPubDeliveryLog::getStats(24),
            'recent_activity' => $this->getRecentActivity(),
        ]);
    }

    /**
     * Get actor statistics.
     */
    private function getActorStats(): array
    {
        return [
            'total' => ActivityPubActor::count(),
            'by_type' => [
                'instance' => ActivityPubActor::where('actor_type', ActivityPubActor::TYPE_INSTANCE)->count(),
                'users' => ActivityPubActor::where('actor_type', ActivityPubActor::TYPE_USER)->count(),
                'groups' => ActivityPubActor::where('actor_type', ActivityPubActor::TYPE_GROUP)->count(),
            ],
        ];
    }

    /**
     * Get follower statistics.
     */
    private function getFollowerStats(): array
    {
        $total = ActivityPubActorFollower::count();

        // Get follower counts by actor type
        $byActorType = ActivityPubActorFollower::query()
            ->join('activitypub_actors', 'activitypub_actor_followers.actor_id', '=', 'activitypub_actors.id')
            ->select('activitypub_actors.actor_type', DB::raw('COUNT(*) as count'))
            ->groupBy('activitypub_actors.actor_type')
            ->pluck('count', 'actor_type')
            ->toArray();

        // Get top instances by follower count (using follower_domain column)
        $topInstances = ActivityPubActorFollower::query()
            ->select('follower_domain as instance', DB::raw('COUNT(*) as count'))
            ->groupBy('follower_domain')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['instance' => $row->instance, 'count' => $row->count])
            ->toArray();

        return [
            'total' => $total,
            'by_actor_type' => [
                'instance' => $byActorType[ActivityPubActor::TYPE_INSTANCE] ?? 0,
                'users' => $byActorType[ActivityPubActor::TYPE_USER] ?? 0,
                'groups' => $byActorType[ActivityPubActor::TYPE_GROUP] ?? 0,
            ],
            'top_instances' => $topInstances,
        ];
    }

    /**
     * Get federated content statistics.
     */
    private function getContentStats(): array
    {
        // Remote users
        $remoteUsers = RemoteUser::count();

        // Remote comments
        $remoteComments = Comment::whereNotNull('remote_user_id')->count();

        // Federation engagement on local posts
        $postStats = Post::query()
            ->selectRaw('
                SUM(federation_likes_count) as total_likes,
                SUM(federation_shares_count) as total_shares,
                SUM(federation_replies_count) as total_replies,
                COUNT(CASE WHEN federation_likes_count > 0 OR federation_shares_count > 0 OR federation_replies_count > 0 THEN 1 END) as posts_with_engagement
            ')
            ->first();

        return [
            'remote_users' => $remoteUsers,
            'remote_comments' => $remoteComments,
            'federation_engagement' => [
                'total_likes' => (int) ($postStats->total_likes ?? 0),
                'total_shares' => (int) ($postStats->total_shares ?? 0),
                'total_replies' => (int) ($postStats->total_replies ?? 0),
                'posts_with_engagement' => (int) ($postStats->posts_with_engagement ?? 0),
            ],
        ];
    }

    /**
     * Get blocked instance statistics.
     */
    private function getBlockedInstanceStats(): array
    {
        $total = ActivityPubBlockedInstance::count();
        $active = ActivityPubBlockedInstance::active()->count();

        $byType = ActivityPubBlockedInstance::active()
            ->select('block_type', DB::raw('COUNT(*) as count'))
            ->groupBy('block_type')
            ->pluck('count', 'block_type')
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'by_type' => [
                'full' => $byType[ActivityPubBlockedInstance::BLOCK_TYPE_FULL] ?? 0,
                'silence' => $byType[ActivityPubBlockedInstance::BLOCK_TYPE_SILENCE] ?? 0,
            ],
        ];
    }

    /**
     * Get recent federation activity.
     */
    private function getRecentActivity(): array
    {
        // Recent remote comments
        $recentRemoteComments = Comment::query()
            ->with(['remoteUser:id,username,instance,avatar_url', 'post:id,title,slug'])
            ->whereNotNull('remote_user_id')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($comment) => [
                'id' => $comment->id,
                'content' => mb_substr($comment->content, 0, 100) . (mb_strlen($comment->content) > 100 ? '...' : ''),
                'created_at' => $comment->created_at->toIso8601String(),
                'remote_user' => $comment->remoteUser ? [
                    'username' => $comment->remoteUser->username,
                    'instance' => $comment->remoteUser->instance,
                ] : null,
                'post' => $comment->post ? [
                    'title' => $comment->post->title,
                    'slug' => $comment->post->slug,
                ] : null,
            ])
            ->toArray();

        // Recent followers
        $recentFollowers = ActivityPubActorFollower::query()
            ->with('actor:id,username,actor_type')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($follower) => [
                'follower_uri' => $follower->follower_uri,
                'followed_at' => $follower->created_at->toIso8601String(),
                'actor' => $follower->actor ? [
                    'username' => $follower->actor->username,
                    'type' => $follower->actor->actor_type,
                ] : null,
            ])
            ->toArray();

        return [
            'recent_remote_comments' => $recentRemoteComments,
            'recent_followers' => $recentFollowers,
        ];
    }

    /**
     * Get posts with federation engagement.
     */
    public function engagedPosts(): JsonResponse
    {
        $posts = Post::query()
            ->with(['user:id,username', 'sub:id,name'])
            ->where(function ($query): void {
                $query->where('federation_likes_count', '>', 0)
                    ->orWhere('federation_shares_count', '>', 0)
                    ->orWhere('federation_replies_count', '>', 0);
            })
            ->orderByRaw('(federation_likes_count + federation_shares_count + federation_replies_count) DESC')
            ->limit(50)
            ->get()
            ->map(fn ($post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'user' => $post->user ? ['username' => $post->user->username] : null,
                'sub' => $post->sub ? ['name' => $post->sub->name] : null,
                'federation_stats' => [
                    'likes' => $post->federation_likes_count,
                    'shares' => $post->federation_shares_count,
                    'replies' => $post->federation_replies_count,
                ],
                'created_at' => $post->created_at->toIso8601String(),
            ]);

        return response()->json(['posts' => $posts]);
    }

    /**
     * Get followers by instance.
     */
    public function followersByInstance(): JsonResponse
    {
        $instances = ActivityPubActorFollower::query()
            ->select('follower_domain as instance')
            ->selectRaw('COUNT(*) as follower_count')
            ->selectRaw('MIN(created_at) as first_follow')
            ->selectRaw('MAX(created_at) as last_follow')
            ->groupBy('follower_domain')
            ->orderByDesc('follower_count')
            ->get()
            ->map(fn ($row) => [
                'instance' => $row->instance,
                'follower_count' => $row->follower_count,
                'first_follow' => $row->first_follow,
                'last_follow' => $row->last_follow,
                'is_blocked' => ActivityPubBlockedInstance::isBlocked($row->instance),
            ]);

        return response()->json(['instances' => $instances]);
    }

    /**
     * Get delivery statistics.
     */
    public function deliveryStats(): JsonResponse
    {
        return response()->json([
            'last_24h' => ActivityPubDeliveryLog::getStats(24),
            'last_7d' => ActivityPubDeliveryLog::getStats(168),
            'all_time' => ActivityPubDeliveryLog::getStats(null),
            'failures_by_instance' => ActivityPubDeliveryLog::getFailuresByInstance(24),
        ]);
    }

    /**
     * Get recent delivery failures.
     */
    public function recentFailures(): JsonResponse
    {
        return response()->json([
            'failures' => ActivityPubDeliveryLog::getRecentFailures(50),
        ]);
    }
}
