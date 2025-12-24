<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RelationshipVoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RelationshipVoteController extends Controller
{
    protected RelationshipVoteService $voteService;

    public function __construct(RelationshipVoteService $voteService)
    {
        $this->voteService = $voteService;
    }

    /**
     * Vote on a relationship.
     * POST /api/v1/relationships/{relationshipId}/vote.
     */
    public function vote(Request $request, int $relationshipId): JsonResponse
    {
        $request->validate([
            'vote' => 'required|integer|in:1,-1',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'You must be logged in to vote.',
            ], 401);
        }

        $result = $this->voteService->vote(
            $relationshipId,
            $user->id,
            (int) $request->input('vote'),
        );

        if ($result['status'] === 'error') {
            return response()->json([
                'message' => $result['message'],
            ], 400);
        }

        $stats = $this->voteService->getVoteStats($relationshipId);

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
            'stats' => $stats,
        ], $result['status'] === 'removed' ? 200 : 201);
    }

    /**
     * Get vote statistics for a relationship.
     * GET /api/v1/relationships/{relationshipId}/votes.
     */
    public function stats(int $relationshipId): JsonResponse
    {
        $stats = $this->voteService->getVoteStats($relationshipId);

        return response()->json([
            'data' => $stats,
        ]);
    }
}
