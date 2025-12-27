<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use const PHP_URL_HOST;

use App\Events\SubMemberJoined;
use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubRequest;
use App\Http\Resources\PostCollection;
use App\Models\Post;
use App\Models\Sub;
use App\Services\ImageService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * API controller for sub (community) CRUD operations.
 * Membership is in SubMembershipController, moderation in SubModerationController.
 */
final class SubController extends Controller
{
    /**
     * Get all subs (communities).
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $category = $request->input('category'); // featured, trending, popular, all
        $mySubs = $request->input('my_subs', false);
        $perPage = $request->input('per_page', 15);

        $query = Sub::query();

        // Search by name or description
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Calculate thresholds for trending and popular (top 20%) - cached for 5 minutes
        [$membersThreshold, $postsThreshold] = Cache::remember('subs_thresholds', 300, function () {
            $totalCount = Sub::count();
            if ($totalCount === 0) {
                return [0, 0];
            }

            $offset = max(0, (int) ($totalCount * 0.2) - 1);

            $membersThreshold = Sub::orderByDesc('members_count')
                ->skip($offset)
                ->value('members_count') ?? 0;

            $postsThreshold = Sub::orderByDesc('posts_count')
                ->skip($offset)
                ->value('posts_count') ?? 0;

            return [$membersThreshold, $postsThreshold];
        });

        // Filter by user's subscriptions
        if ($mySubs && $request->user()) {
            $query->whereHas('subscribers', function ($q) use ($request): void {
                $q->where('user_id', $request->user()->id);
            });
            $query->orderBy('created_at', 'desc');
        } elseif ($category === 'featured') {
            // Featured: manually marked subs
            $query->where('is_featured', true)
                ->orderByDesc('members_count');
        } elseif ($category === 'trending') {
            // Trending: top 20% by posts count (recent activity)
            $query->where('posts_count', '>=', $postsThreshold)
                ->orderByDesc('posts_count');
        } elseif ($category === 'popular') {
            // Popular: top 20% by members count
            $query->where('members_count', '>=', $membersThreshold)
                ->orderByDesc('members_count');
        } else {
            // All: Calculate score where 1 member = 100 posts
            $query->selectRaw('subs.*, (members_count * 100 + posts_count) as score')
                ->orderByDesc('score');
        }

        $subs = $query->paginate($perPage);

        // Add is_member, is_trending, is_popular fields
        if ($request->user()) {
            $userSubIds = $request->user()->subscribedSubs()->pluck('sub_id')->toArray();
            $subs->getCollection()->transform(function ($sub) use ($userSubIds, $membersThreshold, $postsThreshold) {
                $sub->is_member = in_array($sub->id, $userSubIds);
                $sub->is_trending = $sub->posts_count >= $postsThreshold && $postsThreshold > 0;
                $sub->is_popular = $sub->members_count >= $membersThreshold && $membersThreshold > 0;

                return $sub;
            });
        } else {
            $subs->getCollection()->transform(function ($sub) use ($membersThreshold, $postsThreshold) {
                $sub->is_member = false;
                $sub->is_trending = $sub->posts_count >= $postsThreshold && $postsThreshold > 0;
                $sub->is_popular = $sub->members_count >= $membersThreshold && $membersThreshold > 0;

                return $sub;
            });
        }

        return response()->json([
            'data' => $subs->items(),
            'meta' => [
                'current_page' => $subs->currentPage(),
                'last_page' => $subs->lastPage(),
                'per_page' => $subs->perPage(),
                'total' => $subs->total(),
            ],
        ]);
    }

    /**
     * Get a specific sub.
     */
    public function show(Request $request, string $nameOrId): JsonResponse
    {
        // Find sub by name or ID
        $sub = is_numeric($nameOrId)
            ? Sub::find($nameOrId)
            : Sub::where('name', $nameOrId)->first();

        if (! $sub) {
            return response()->json([
                'error' => 'Sub not found',
                'message' => __('subs.not_found_create'),
                'name' => $nameOrId,
            ], 404);
        }

        // Load relationships
        $sub->load('creator');

        // Check if user is member
        $isMember = false;
        $isModerator = false;
        $isOwner = false;
        if ($request->user()) {
            $isMember = $sub->subscribers()->where('user_id', $request->user()->id)->exists();
            $isModerator = $sub->isModerator($request->user());
            $isOwner = $sub->isOwner($request->user());
        }

        $sub->is_member = $isMember;
        $sub->is_moderator = $isModerator;
        $sub->is_owner = $isOwner;

        // Check if sub is orphaned and can be claimed
        $sub->is_orphaned = $sub->isOrphaned();
        $sub->can_claim = false;
        $sub->has_claim_priority = false;
        if ($sub->is_orphaned && $request->user()) {
            $sub->can_claim = $sub->canClaimOwnership($request->user());
            $sub->has_claim_priority = $sub->hasClaimPriority($request->user());
        }

        // Calculate is_trending and is_popular based on top 20% - cached for 5 minutes
        [$membersThreshold, $postsThreshold] = Cache::remember('subs_thresholds', 300, function () {
            $totalCount = Sub::count();
            if ($totalCount === 0) {
                return [0, 0];
            }

            $offset = max(0, (int) ($totalCount * 0.2) - 1);

            $membersThreshold = Sub::orderByDesc('members_count')
                ->skip($offset)
                ->value('members_count') ?? 0;

            $postsThreshold = Sub::orderByDesc('posts_count')
                ->skip($offset)
                ->value('posts_count') ?? 0;

            return [$membersThreshold, $postsThreshold];
        });

        $sub->is_trending = $sub->posts_count >= $postsThreshold && $postsThreshold > 0;
        $sub->is_popular = $sub->members_count >= $membersThreshold && $membersThreshold > 0;

        // Parse rules if stored as JSON
        if ($sub->rules && is_string($sub->rules)) {
            $sub->rules = json_decode($sub->rules, true);
        }

        // Add federation info
        $sub->federation_enabled = false;
        $sub->fediverse_handle = null;
        if (config('activitypub.enabled', false)) {
            $subSettings = \App\Models\ActivityPubSubSettings::where('sub_id', $sub->id)->first();
            if ($subSettings !== null && $subSettings->federation_enabled) {
                $sub->federation_enabled = true;
                $publicDomain = parse_url((string) config('activitypub.public_domain', config('activitypub.domain')), PHP_URL_HOST);
                $sub->fediverse_handle = "!{$sub->name}@{$publicDomain}";
            }
        }

        // Load public moderators if not hidden
        $sub->public_moderators = [];
        if (! $sub->hide_moderators) {
            $moderators = $sub->moderators()
                ->select('users.id', 'users.username', 'users.avatar')
                ->withPivot('is_owner')
                ->orderByPivot('is_owner', 'desc')
                ->orderByPivot('created_at', 'asc')
                ->limit(5)
                ->get();

            // Add creator if not in list
            $creatorInList = $moderators->contains('id', $sub->created_by);
            if (! $creatorInList && $sub->creator) {
                $creator = (object) [
                    'id' => $sub->creator->id,
                    'username' => $sub->creator->username,
                    'avatar' => $sub->creator->avatar,
                    'pivot' => (object) ['is_owner' => true],
                ];
                $moderators->prepend($creator);
            }

            $sub->public_moderators = $moderators->take(5)->values();
        }

        return response()->json([
            'data' => $sub,
        ]);
    }

    /**
     * Get posts from a specific sub.
     */
    public function posts(Request $request, string $subId): PostCollection|JsonResponse
    {
        // Find sub
        $sub = is_numeric($subId)
            ? Sub::find($subId)
            : Sub::where('name', $subId)->first();

        if (! $sub) {
            return response()->json([
                'error' => 'Sub not found',
                'message' => __('subs.not_found'),
            ], 404);
        }

        // Check if sub is private and user is not an active member
        if ($sub->is_private) {
            $isMember = $request->user() && $sub->subscribers()
                ->where('user_id', $request->user()->id)
                ->wherePivot('status', Sub::MEMBERSHIP_ACTIVE)
                ->exists();
            if (! $isMember) {
                return response()->json([
                    'error' => 'Private community',
                    'message' => __('subs.private_community'),
                    'is_private' => true,
                ], 403);
            }
        }

        $perPage = $request->input('per_page', 10);
        $sort = $request->input('sort', 'new');

        // Get posts from this sub
        $query = $sub->posts()
            ->where('status', Post::STATUS_PUBLISHED)
            ->with(['user', 'sub', 'tags'])
            ->withCount('comments');

        // Apply sorting
        switch ($sort) {
            case 'hot':
                $query->orderByDesc('score');
                break;
            case 'top':
                $query->orderByDesc('upvotes');
                break;
            case 'new':
            default:
                $query->orderByDesc('created_at');
                break;
        }

        $posts = $query->paginate($perPage);

        return new PostCollection($posts);
    }

    /**
     * Create a new sub.
     */
    public function store(StoreSubRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $sub = Sub::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'rules' => $validated['rules'] ?? null,
            'icon' => $validated['icon'] ?? '📝',
            'color' => $validated['color'] ?? '#6366F1',
            'is_private' => $validated['is_private'] ?? false,
            'is_adult' => $validated['is_adult'] ?? false,
            'visibility' => $validated['visibility'] ?? 'visible',
            'created_by' => $request->user()->id,
            'members_count' => 1,
            'posts_count' => 0,
        ]);

        // Automatically subscribe the creator
        $sub->subscribers()->attach($request->user()->id);

        // Fire new member event (the creator)
        event(new SubMemberJoined($sub, $request->user()));

        // Enable federation by default for new subs
        if (config('activitypub.enabled', false)) {
            \App\Models\ActivityPubSubSettings::create([
                'sub_id' => $sub->id,
                'federation_enabled' => true,
                'auto_announce_posts' => true,
            ]);
        }

        // Unlock first community achievement
        $achievementService = app(\App\Services\AchievementService::class);
        $achievementService->unlockIfExists($request->user(), 'first_sub');

        // Load the creator relationship
        $sub->load('creator');

        return response()->json([
            'message' => __('subs.created'),
            'data' => $sub,
        ], 201);
    }

    /**
     * Update a sub.
     */
    public function update(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if user is the owner (only owner can update settings)
        if (! $sub->isOwner($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_update'),
            ], 403);
        }

        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'sometimes|string|max:500',
            'color' => 'sometimes|string|max:7',
            'is_private' => 'sometimes|boolean',
            'is_adult' => 'sometimes|boolean',
            'require_approval' => 'sometimes|boolean',
            'hide_owner' => 'sometimes|boolean',
            'hide_moderators' => 'sometimes|boolean',
            'allowed_content_types' => 'nullable|array',
            'allowed_content_types.*' => 'string|in:text,link,image,video,audio,poll',
            'rules' => 'nullable|string',
        ]);

        $sub->update($validated);

        // Reload relationships
        $sub->refresh();
        $sub->load('creator');

        // Add computed fields
        $sub->is_member = $sub->subscribers()->where('user_id', $request->user()->id)->exists();
        $sub->is_moderator = true;

        return response()->json([
            'message' => __('subs.updated'),
            'data' => $sub,
        ]);
    }

    /**
     * Delete a sub (owner only).
     */
    public function destroy(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        if (! $request->user() || ! $sub->isOwner($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_delete'),
            ], 403);
        }

        $sub->delete();

        return response()->json([
            'message' => __('subs.deleted'),
            'id' => (int) $subId,
        ]);
    }

    /**
     * Upload icon for a sub.
     */
    public function uploadIcon(Request $request, string $subId, ImageService $imageService): JsonResponse
    {
        $request->validate([
            'icon' => 'required|image|mimes:jpeg,png,gif,webp|max:16384', // 16MB
        ]);

        $sub = Sub::findOrFail($subId);

        // Check if user is the owner (only owner can change icon)
        if (! $sub->isOwner($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_update'),
            ], 403);
        }

        try {
            $image = $imageService->uploadSubIcon(
                $request->file('icon'),
                (int) $subId,
                $request->user()->id,
            );

            // Update sub model with new icon URL
            $sub->icon = $image->getUrl();
            $sub->save();

            return response()->json([
                'message' => __('subs.icon_uploaded'),
                'icon_url' => $image->getUrl(),
                'image' => [
                    'id' => $image->id,
                    'urls' => $image->getUrls(),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => ErrorHelper::getSafeMessage($e, __('subs.icon_validation_error')),
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('subs.icon_upload_failed'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    /**
     * Get rules for a sub.
     */
    public function rules(string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        return response()->json([
            'data' => $sub->rules,
            'sub_id' => (int) $subId,
        ]);
    }
}
