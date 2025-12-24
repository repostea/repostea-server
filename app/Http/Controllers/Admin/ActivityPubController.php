<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityPubDelivery;
use App\Models\ActivityPubFollower;
use App\Models\Post;
use App\Services\ActivityPubService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ActivityPubController extends Controller
{
    public function __construct(
        private readonly ActivityPubService $activityPub,
    ) {}

    public function index(Request $request): View
    {
        // Get filter parameters
        $statusFilter = is_string($request->query('status')) ? $request->query('status') : null;
        $domainFilter = is_string($request->query('domain')) ? $request->query('domain') : null;
        $perPage = (int) $request->query('per_page', 25);

        // Get followers with optional domain filter
        $followersQuery = ActivityPubFollower::query()
            ->orderByDesc('followed_at');

        if ($domainFilter !== null && $domainFilter !== '') {
            $followersQuery->where('domain', 'like', "%{$domainFilter}%");
        }

        $followers = $followersQuery->get();

        // Get unique domains for filter dropdown
        $domains = ActivityPubFollower::query()
            ->select('domain')
            ->distinct()
            ->orderBy('domain')
            ->pluck('domain');

        // Build deliveries query with filters
        $deliveriesQuery = ActivityPubDelivery::query()
            ->orderByDesc('created_at');

        if ($statusFilter !== null && in_array($statusFilter, ['pending', 'delivered', 'failed'], true)) {
            $deliveriesQuery->where('status', $statusFilter);
        }

        // Paginate deliveries
        $deliveriesPaginated = $deliveriesQuery->paginate($perPage)->withQueryString();

        // Map deliveries to include post info
        $deliveries = $deliveriesPaginated->through(function ($delivery) {
            $postId = null;
            $post = null;

            if (preg_match('/activities\/(\d+)/', $delivery->activity_id, $matches)) {
                $postId = (int) $matches[1];
                $post = Post::find($postId);
            }

            return (object) [
                'id' => $delivery->id,
                'activity_id' => $delivery->activity_id,
                'target_inbox' => $delivery->target_inbox,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'last_error' => $delivery->last_error,
                'delivered_at' => $delivery->delivered_at,
                'created_at' => $delivery->created_at,
                'post_id' => $postId,
                'post' => $post,
            ];
        });

        // Get statistics
        $stats = [
            'followers' => ActivityPubFollower::count(),
            'total_deliveries' => ActivityPubDelivery::count(),
            'delivered' => ActivityPubDelivery::where('status', 'delivered')->count(),
            'pending' => ActivityPubDelivery::where('status', 'pending')->count(),
            'failed' => ActivityPubDelivery::where('status', 'failed')->count(),
        ];

        // Get configuration status
        $config = [
            'enabled' => $this->activityPub->isEnabled(),
            'actor_id' => $this->activityPub->isEnabled() ? $this->activityPub->getActorId() : null,
            'username' => $this->activityPub->isEnabled() ? $this->activityPub->getUsername() : null,
        ];

        // Current filters for view
        $filters = [
            'status' => $statusFilter,
            'domain' => $domainFilter,
            'per_page' => $perPage,
        ];

        return view('admin.activitypub.index', compact('followers', 'deliveries', 'stats', 'config', 'filters', 'domains'));
    }
}
