<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use const PHP_URL_HOST;

use App\Http\Controllers\Controller;
use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubBlockedInstance;
use App\Models\ActivityPubDeliveryLog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\RemoteUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin controller for federation management (web views).
 */
final class AdminFederationController extends Controller
{
    /**
     * Display blocked instances management page.
     */
    public function blocked(Request $request): View
    {
        $query = ActivityPubBlockedInstance::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by block type
        if ($request->filled('block_type')) {
            $query->where('block_type', $request->block_type);
        }

        // Search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request): void {
                $q->where('domain', 'like', '%' . $request->search . '%')
                    ->orWhere('reason', 'like', '%' . $request->search . '%');
            });
        }

        $blockedInstances = $query->orderByDesc('created_at')->paginate(25);

        // Stats
        $stats = [
            'total' => ActivityPubBlockedInstance::count(),
            'active' => ActivityPubBlockedInstance::where('is_active', true)->count(),
            'full_blocks' => ActivityPubBlockedInstance::where('is_active', true)
                ->where('block_type', ActivityPubBlockedInstance::BLOCK_TYPE_FULL)->count(),
            'silenced' => ActivityPubBlockedInstance::where('is_active', true)
                ->where('block_type', ActivityPubBlockedInstance::BLOCK_TYPE_SILENCE)->count(),
        ];

        return view('admin.activitypub.blocked', [
            'blockedInstances' => $blockedInstances,
            'stats' => $stats,
            'filters' => [
                'status' => $request->status,
                'block_type' => $request->block_type,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Store a new blocked instance.
     */
    public function storeBlocked(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
            'block_type' => ['required', 'in:full,silence'],
        ]);

        // Normalize domain (extract from URL if needed)
        $domain = strtolower($validated['domain']);
        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            $domain = (string) parse_url($domain, PHP_URL_HOST);
        }

        // Check if already blocked
        if (ActivityPubBlockedInstance::where('domain', $domain)->exists()) {
            return back()->with('error', "Domain '{$domain}' is already in the block list.");
        }

        ActivityPubBlockedInstance::blockDomain(
            $domain,
            $validated['reason'] ?? null,
            $validated['block_type'],
        );

        return back()->with('success', "Instance '{$domain}' has been blocked.");
    }

    /**
     * Update a blocked instance.
     */
    public function updateBlocked(Request $request, ActivityPubBlockedInstance $blockedInstance): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'block_type' => ['required', 'in:full,silence'],
            'is_active' => ['required', 'boolean'],
        ]);

        $blockedInstance->update($validated);

        // Clear cache
        ActivityPubBlockedInstance::clearCache();

        return back()->with('success', "Block for '{$blockedInstance->domain}' has been updated.");
    }

    /**
     * Remove a blocked instance.
     */
    public function destroyBlocked(ActivityPubBlockedInstance $blockedInstance): RedirectResponse
    {
        $domain = $blockedInstance->domain;
        $blockedInstance->delete();

        // Clear cache
        ActivityPubBlockedInstance::clearCache();

        return back()->with('success', "Instance '{$domain}' has been unblocked.");
    }

    /**
     * Display federation statistics page.
     */
    public function stats(): View
    {
        // Actor stats
        $actorStats = [
            'total' => ActivityPubActor::count(),
            'instance' => ActivityPubActor::where('actor_type', ActivityPubActor::TYPE_INSTANCE)->count(),
            'users' => ActivityPubActor::where('actor_type', ActivityPubActor::TYPE_USER)->count(),
            'groups' => ActivityPubActor::where('actor_type', ActivityPubActor::TYPE_GROUP)->count(),
        ];

        // Follower stats
        $followerStats = [
            'total' => ActivityPubActorFollower::count(),
        ];

        // Top instances by follower count
        $topInstances = ActivityPubActorFollower::query()
            ->select('follower_domain as instance', DB::raw('COUNT(*) as count'))
            ->groupBy('follower_domain')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Content stats
        $contentStats = [
            'remote_users' => RemoteUser::count(),
            'remote_comments' => Comment::whereNotNull('remote_user_id')->count(),
        ];

        // Federation engagement on local posts
        $engagementStats = Post::query()
            ->selectRaw('
                SUM(federation_likes_count) as total_likes,
                SUM(federation_shares_count) as total_shares,
                SUM(federation_replies_count) as total_replies,
                COUNT(CASE WHEN federation_likes_count > 0 OR federation_shares_count > 0 OR federation_replies_count > 0 THEN 1 END) as posts_with_engagement
            ')
            ->first();

        // Delivery stats (24h)
        $deliveryStats = ActivityPubDeliveryLog::getStats(24);

        // Recent failures
        $recentFailures = ActivityPubDeliveryLog::getRecentFailures(10);

        // Blocked instance stats
        $blockedStats = [
            'total' => ActivityPubBlockedInstance::count(),
            'active' => ActivityPubBlockedInstance::where('is_active', true)->count(),
            'full' => ActivityPubBlockedInstance::where('is_active', true)
                ->where('block_type', ActivityPubBlockedInstance::BLOCK_TYPE_FULL)->count(),
            'silence' => ActivityPubBlockedInstance::where('is_active', true)
                ->where('block_type', ActivityPubBlockedInstance::BLOCK_TYPE_SILENCE)->count(),
        ];

        return view('admin.activitypub.stats', [
            'actorStats' => $actorStats,
            'followerStats' => $followerStats,
            'topInstances' => $topInstances,
            'contentStats' => $contentStats,
            'engagementStats' => [
                'likes' => (int) ($engagementStats->total_likes ?? 0),
                'shares' => (int) ($engagementStats->total_shares ?? 0),
                'replies' => (int) ($engagementStats->total_replies ?? 0),
                'posts_with_engagement' => (int) ($engagementStats->posts_with_engagement ?? 0),
            ],
            'deliveryStats' => $deliveryStats,
            'recentFailures' => $recentFailures,
            'blockedStats' => $blockedStats,
        ]);
    }
}
