<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PostRelationship;
use App\Models\RelationshipVote;
use Exception;
use Illuminate\Support\Facades\DB;
use Log;

final class RelationshipVoteService
{
    public function __construct(
        private readonly RelationshipAchievementChecker $achievementChecker,
    ) {}

    /**
     * Vote on a relationship.
     *
     * @param  int  $relationshipId  The relationship to vote on
     * @param  int  $userId  The user casting the vote
     * @param  int  $voteValue  1 for upvote, -1 for downvote
     *
     * @return array{status: string, message: string, vote?: RelationshipVote}
     */
    public function vote(int $relationshipId, int $userId, int $voteValue): array
    {
        // Validate vote value
        if (! in_array($voteValue, [1, -1])) {
            return [
                'status' => 'error',
                'message' => 'Invalid vote value. Must be 1 or -1.',
            ];
        }

        $relationship = PostRelationship::find($relationshipId);
        if (! $relationship) {
            return [
                'status' => 'error',
                'message' => 'Relationship not found.',
            ];
        }

        // Check if user already voted
        $existingVote = RelationshipVote::where('relationship_id', $relationshipId)
            ->where('user_id', $userId)
            ->first();

        DB::beginTransaction();

        try {
            if ($existingVote) {
                // If same vote, remove it (toggle)
                if ($existingVote->vote === $voteValue) {
                    $existingVote->delete();
                    $relationship->updateVoteCounts();
                    DB::commit();

                    return [
                        'status' => 'removed',
                        'message' => 'Vote removed successfully.',
                    ];
                }

                // Change vote
                $existingVote->vote = $voteValue;
                $existingVote->save();
            } else {
                // Create new vote
                $existingVote = RelationshipVote::create([
                    'relationship_id' => $relationshipId,
                    'user_id' => $userId,
                    'vote' => $voteValue,
                ]);
            }

            $relationship->updateVoteCounts();
            DB::commit();

            // Check for achievements after successful vote
            try {
                $this->achievementChecker->checkAfterVote($relationshipId);
            } catch (Exception $e) {
                // Log but don't fail the vote if achievement check fails
                Log::warning('Failed to check achievements after relationship vote', [
                    'relationship_id' => $relationshipId,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'status' => 'success',
                'message' => 'Vote registered successfully.',
                'vote' => $existingVote->fresh(),
            ];
        } catch (Exception $e) {
            DB::rollBack();

            return [
                'status' => 'error',
                'message' => 'Failed to register vote: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get user's vote for a relationship.
     */
    public function getUserVote(int $relationshipId, int $userId): ?RelationshipVote
    {
        return RelationshipVote::where('relationship_id', $relationshipId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Get vote statistics for a relationship.
     *
     * @return array{upvotes: int, downvotes: int, score: int}
     */
    public function getVoteStats(int $relationshipId): array
    {
        $relationship = PostRelationship::find($relationshipId);

        if (! $relationship) {
            return [
                'upvotes' => 0,
                'downvotes' => 0,
                'score' => 0,
            ];
        }

        return [
            'upvotes' => $relationship->upvotes_count,
            'downvotes' => $relationship->downvotes_count,
            'score' => $relationship->score,
        ];
    }
}
