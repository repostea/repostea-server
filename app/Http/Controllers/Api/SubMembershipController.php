<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sub;
use App\Services\SubMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for sub (community) membership management.
 */
final class SubMembershipController extends Controller
{
    public function __construct(
        private readonly SubMembershipService $membershipService,
    ) {}

    /**
     * Join a sub.
     */
    public function join(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);
        $result = $this->membershipService->join(
            $sub,
            $request->user(),
            $request->input('message'),
        );

        return response()->json([
            'message' => $result['message'],
            'sub_id' => $sub->id,
            'is_member' => $result['is_member'],
            'request_pending' => $result['request_pending'],
            'member_count' => $result['member_count'],
        ]);
    }

    /**
     * Leave a sub.
     */
    public function leave(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);
        $result = $this->membershipService->leave($sub, $request->user());

        return response()->json([
            'message' => $result['message'],
            'sub_id' => $sub->id,
            'is_member' => $result['is_member'],
            'member_count' => $result['member_count'],
        ]);
    }

    /**
     * Create membership request for private sub.
     */
    public function createMembershipRequest(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        $result = $this->membershipService->join(
            $sub,
            $request->user(),
            $request->input('message'),
        );

        if ($result['is_member']) {
            return response()->json([
                'message' => $result['message'],
                'sub_id' => $sub->id,
            ], 409);
        }

        if (! $result['request_pending']) {
            return response()->json([
                'message' => $result['message'],
                'sub_id' => $sub->id,
                'is_member' => true,
                'member_count' => $result['member_count'],
            ]);
        }

        return response()->json([
            'message' => $result['message'],
            'sub_id' => $sub->id,
            'request_pending' => true,
        ], 201);
    }

    /**
     * Get members of a sub.
     */
    public function members(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        // Check if sub is private and user is not an active member
        if ($sub->is_private && ! $this->membershipService->isActiveMember($sub, $request->user())) {
            return response()->json([
                'error' => 'Private community',
                'message' => __('subs.private_community'),
            ], 403);
        }

        $members = $this->membershipService->getMembers($sub);

        return response()->json([
            'data' => $members->items(),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    /**
     * Remove a member from the sub (moderators only).
     */
    public function removeMember(Request $request, string $subId, string $userId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_remove_member'),
            ], 403);
        }

        $result = $this->membershipService->removeMember($sub, (int) $userId);

        if (! $result['success']) {
            return response()->json([
                'error' => 'Invalid operation',
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'member_count' => $result['member_count'],
        ]);
    }

    /**
     * Get pending membership requests (moderators only).
     */
    public function membershipRequests(Request $request, string $subId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_moderator'),
            ], 403);
        }

        $requests = $this->membershipService->getPendingRequests($sub, 20);

        return response()->json([
            'data' => $requests->items(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Approve a membership request (moderators only).
     */
    public function approveMembershipRequest(Request $request, string $subId, string $userId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_moderator'),
            ], 403);
        }

        $result = $this->membershipService->approveMembershipRequest($sub, (int) $userId);

        if (! $result['success']) {
            return response()->json([
                'error' => 'Not found',
                'message' => $result['message'],
            ], 404);
        }

        return response()->json([
            'message' => $result['message'],
            'member_count' => $result['member_count'],
        ]);
    }

    /**
     * Reject a membership request (moderators only).
     */
    public function rejectMembershipRequest(Request $request, string $subId, string $userId): JsonResponse
    {
        $sub = Sub::findOrFail($subId);

        if (! $request->user() || ! $sub->isModerator($request->user())) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => __('subs.unauthorized_moderator'),
            ], 403);
        }

        $result = $this->membershipService->rejectMembershipRequest($sub, (int) $userId);

        if (! $result['success']) {
            return response()->json([
                'error' => 'Not found',
                'message' => $result['message'],
            ], 404);
        }

        return response()->json([
            'message' => $result['message'],
        ]);
    }
}
