<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Sub;
use App\Services\SubModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for sub (community) moderation.
 */
final class SubModerationController extends Controller
{
    public function __construct(
        private readonly SubModerationService $moderationService,
    ) {}

    /**
     * Get pending posts for moderation (moderators only).
     */
    public function pendingPosts(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if user is moderator
        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_view_pending'),
            ], 403);
        }

        $posts = $sub->posts()
            ->where('status', Post::STATUS_PENDING)
            ->with(['user', 'tags'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $posts->items(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Approve a pending post (moderators only).
     */
    public function approvePost(Request $request, string $subId, string $postId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if user is moderator
        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_approve'),
            ], 403);
        }

        $post = $sub->posts()->where('id', $postId)->firstOrFail();
        $post->update(['status' => Post::STATUS_PUBLISHED]);

        return response()->json([
            'message' => __('subs.post_approved'),
            'data' => $post,
        ]);
    }

    /**
     * Reject a pending post (moderators only).
     */
    public function rejectPost(Request $request, string $subId, string $postId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if user is moderator
        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_reject'),
            ], 403);
        }

        $post = $sub->posts()->where('id', $postId)->firstOrFail();
        $post->update(['status' => Post::STATUS_HIDDEN]);

        return response()->json([
            'message' => __('subs.post_rejected'),
            'data' => $post,
        ]);
    }

    /**
     * Get moderators of a sub.
     */
    public function moderators(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if user is moderator (only moderators can see the full list)
        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_moderator'),
            ], 403);
        }

        $moderators = $this->moderationService->getModerators($sub);

        return response()->json([
            'data' => $moderators,
        ]);
    }

    /**
     * Add a moderator to the sub (owner only).
     */
    public function addModerator(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if user is the owner
        if (! $request->user() || ! $sub->isOwner($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_owner'),
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $result = $this->moderationService->addModerator($sub, $validated['user_id'], $request->user());

        if (! $result['success']) {
            return response()->json([
                'error' => 'Invalid operation',
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['user'],
        ]);
    }

    /**
     * Remove a moderator from the sub (owner only).
     */
    public function removeModerator(Request $request, string $subId, string $userId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if user is the owner
        if (! $request->user() || ! $sub->isOwner($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_owner'),
            ], 403);
        }

        $result = $this->moderationService->removeModerator($sub, (int) $userId);

        if (! $result['success']) {
            return response()->json([
                'error' => 'Invalid operation',
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'message' => $result['message'],
        ]);
    }

    /**
     * Hide a published post (moderators only).
     */
    public function hidePost(Request $request, string $subId, string $postId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if user is moderator
        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_moderator'),
            ], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $post = $sub->posts()->where('id', $postId)->firstOrFail();

        // Store previous status and hide
        $post->update([
            'previous_status' => $post->status,
            'status' => Post::STATUS_HIDDEN,
            'moderated_by' => $request->user()->id,
            'moderation_reason' => $validated['reason'] ?? null,
            'moderated_at' => now(),
        ]);

        return response()->json([
            'message' => __('subs.post_hidden'),
            'data' => $post,
        ]);
    }

    /**
     * Unhide a hidden post (moderators only).
     */
    public function unhidePost(Request $request, string $subId, string $postId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if user is moderator
        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_moderator'),
            ], 403);
        }

        $post = $sub->posts()->where('id', $postId)->firstOrFail();

        // Restore to previous status or published
        $previousStatus = $post->previous_status ?? Post::STATUS_PUBLISHED;
        $post->update([
            'status' => $previousStatus,
            'previous_status' => null,
            'moderated_by' => null,
            'moderation_reason' => null,
            'moderated_at' => null,
        ]);

        return response()->json([
            'message' => __('subs.post_unhidden'),
            'data' => $post,
        ]);
    }

    /**
     * Get hidden posts for a sub (moderators only).
     */
    public function hiddenPosts(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if user is moderator
        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_moderator'),
            ], 403);
        }

        $posts = $sub->posts()
            ->where('status', Post::STATUS_HIDDEN)
            ->with(['user', 'moderatedBy'])
            ->orderBy('moderated_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $posts->items(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Claim ownership of an orphaned sub.
     * Priority: moderators first, then any member.
     */
    public function claimOwnership(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('auth.unauthenticated'),
            ], 401);
        }

        $result = $this->moderationService->claimOwnership($sub, $user);

        if (! $result['success']) {
            // Determine error type and status code based on failure reason
            if (str_contains($result['message'], 'not_orphaned')) {
                return response()->json([
                    'error' => 'Not orphaned',
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'error' => 'Cannot claim',
                'message' => $result['message'],
            ], 403);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['sub'],
        ]);
    }

    /**
     * Check if a sub can be claimed and by whom.
     */
    public function claimStatus(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        $status = $this->moderationService->getClaimStatus($sub, $request->user());

        return response()->json([
            ...$status,
            'grace_period_total_days' => Sub::MODERATOR_CLAIM_PRIORITY_DAYS,
        ]);
    }
}
