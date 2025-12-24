<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PollVote;
use App\Models\Post;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PollController extends Controller
{
    /**
     * Get poll results for a specific post.
     */
    public function getResults(Request $request, $postId): JsonResponse
    {
        try {
            $post = Post::findOrFail($postId);

            // Verify it's a poll post
            if ($post->content_type !== 'poll') {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.not_a_poll'),
                ], 400);
            }

            $metadata = $post->media_metadata;

            // Parse JSON if it's a string
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true);
            }

            if (! $metadata || ! isset($metadata['poll_options']) || ! is_array($metadata['poll_options'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.options_not_found'),
                ], 400);
            }

            // Get vote counts for each option
            $voteCounts = PollVote::where('post_id', $postId)
                ->select('option_number', DB::raw('count(*) as votes'))
                ->groupBy('option_number')
                ->pluck('votes', 'option_number')
                ->toArray();

            $totalVotes = array_sum($voteCounts);

            // Build options array with results
            $options = [];
            foreach ($metadata['poll_options'] as $index => $text) {
                $optionNumber = $index + 1;
                $votes = $voteCounts[$optionNumber] ?? 0;
                $percentage = $totalVotes > 0 ? round(($votes / $totalVotes) * 100, 1) : 0;

                $options[] = [
                    'id' => $optionNumber,
                    'text' => $text,
                    'votes' => $votes,
                    'percentage' => $percentage,
                ];
            }

            // Check if poll is expired
            $expired = false;
            if (isset($metadata['expires_at'])) {
                $expired = now()->isAfter($metadata['expires_at']);
            }

            // Check if current user has voted (only for authenticated users)
            $userHasVoted = false;
            $userVotes = [];

            if ($request->user()) {
                $userVotes = PollVote::where('post_id', $postId)
                    ->where('user_id', $request->user()->id)
                    ->pluck('option_number')
                    ->toArray();
                $userHasVoted = count($userVotes) > 0;
            }

            return response()->json([
                'success' => true,
                'total_votes' => $totalVotes,
                'options' => $options,
                'expired' => $expired,
                'expires_at' => $metadata['expires_at'] ?? null,
                'allow_multiple_options' => $metadata['allow_multiple_options'] ?? false,
                'user_has_voted' => $userHasVoted,
                'user_votes' => $userVotes,
            ]);
        } catch (Exception $e) {
            Log::error('Error getting poll results', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('messages.polls.error_loading'),
            ], 500);
        }
    }

    /**
     * Vote on a poll option.
     */
    public function vote(Request $request, $postId, $optionNumber): JsonResponse
    {
        try {
            $post = Post::findOrFail($postId);

            // Verify it's a poll post
            if ($post->content_type !== 'poll') {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.not_a_poll'),
                ], 400);
            }

            $metadata = $post->media_metadata;

            // Parse JSON if it's a string
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true);
            }

            if (! $metadata || ! isset($metadata['poll_options'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.options_not_found'),
                ], 400);
            }

            // Verify option exists
            if ($optionNumber < 1 || $optionNumber > count($metadata['poll_options'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.invalid_option'),
                ], 400);
            }

            // Check if poll is expired
            if (isset($metadata['expires_at']) && now()->isAfter($metadata['expires_at'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.expired'),
                ], 400);
            }

            // Require authentication
            if (! $request->user()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.login_required_vote'),
                ], 401);
            }

            $userId = $request->user()->id;
            $ipAddress = $request->ip();

            // Check if already voted on this option
            $existingVote = PollVote::where('post_id', $postId)
                ->where('option_number', $optionNumber)
                ->where('user_id', $userId)
                ->first();

            if ($existingVote) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.already_voted'),
                ], 400);
            }

            // If multiple options are not allowed, remove any existing votes
            if (! ($metadata['allow_multiple_options'] ?? false)) {
                PollVote::where('post_id', $postId)
                    ->where('user_id', $userId)
                    ->delete();
            }

            // Create the vote
            PollVote::create([
                'post_id' => $postId,
                'option_number' => $optionNumber,
                'user_id' => $userId,
                'device_fingerprint' => null,
                'ip_address' => $ipAddress,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.polls.vote_recorded'),
            ]);
        } catch (Exception $e) {
            Log::error('Error voting on poll', [
                'post_id' => $postId,
                'option_number' => $optionNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('messages.polls.error_voting'),
            ], 500);
        }
    }

    /**
     * Remove user's vote from a poll.
     */
    public function removeVote(Request $request, $postId): JsonResponse
    {
        try {
            $post = Post::findOrFail($postId);

            // Verify it's a poll post
            if ($post->content_type !== 'poll') {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.not_a_poll'),
                ], 400);
            }

            // Require authentication
            if (! $request->user()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.login_required_remove'),
                ], 401);
            }

            $userId = $request->user()->id;

            // Delete all votes from this user for this poll
            $deleted = PollVote::where('post_id', $postId)
                ->where('user_id', $userId)
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.polls.no_votes_to_remove'),
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => __('messages.polls.vote_removed'),
            ]);
        } catch (Exception $e) {
            Log::error('Error removing poll vote', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('messages.polls.error_removing'),
            ], 500);
        }
    }
}
