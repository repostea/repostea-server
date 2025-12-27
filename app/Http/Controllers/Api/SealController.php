<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckUserMarksRequest;
use App\Http\Requests\SealMarkTypeRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Services\SealService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SealController extends Controller
{
    protected SealService $sealService;

    public function __construct(SealService $sealService)
    {
        $this->sealService = $sealService;
    }

    /**
     * Get user's available seals.
     */
    public function getUserSeals(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userSeals = $this->sealService->getUserSeals($user);

        return response()->json([
            'available_seals' => $userSeals->available_seals,
            'total_earned' => $userSeals->total_earned,
            'total_used' => $userSeals->total_used,
            'last_awarded_at' => $userSeals->last_awarded_at,
        ]);
    }

    /**
     * Apply seal mark to a post.
     */
    public function markPost(SealMarkTypeRequest $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->sealService->applySealMark($user, $post, $request->validated()['type']);

            return response()->json([
                'success' => true,
                'message' => __('seals.applied_successfully'),
                'available_seals' => $result['available_seals'],
                'post' => [
                    'recommended_seals_count' => $post->fresh()->recommended_seals_count,
                    'advise_against_seals_count' => $post->fresh()->advise_against_seals_count,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ErrorHelper::getSafeMessage($e, __('seals.error_applying')),
            ], 400);
        }
    }

    /**
     * Remove seal mark from a post.
     */
    public function unmarkPost(SealMarkTypeRequest $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->sealService->removeSealMark($user, $post, $request->validated()['type']);

            return response()->json([
                'success' => true,
                'message' => __('seals.removed_successfully'),
                'available_seals' => $result['available_seals'],
                'post' => [
                    'recommended_seals_count' => $post->fresh()->recommended_seals_count,
                    'advise_against_seals_count' => $post->fresh()->advise_against_seals_count,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ErrorHelper::getSafeMessage($e, __('seals.error_removing')),
            ], 400);
        }
    }

    /**
     * Apply seal mark to a comment.
     */
    public function markComment(SealMarkTypeRequest $request, Comment $comment): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->sealService->applySealMark($user, $comment, $request->validated()['type']);

            return response()->json([
                'success' => true,
                'message' => __('seals.applied_successfully'),
                'available_seals' => $result['available_seals'],
                'comment' => [
                    'recommended_seals_count' => $comment->fresh()->recommended_seals_count,
                    'advise_against_seals_count' => $comment->fresh()->advise_against_seals_count,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ErrorHelper::getSafeMessage($e, __('seals.error_applying')),
            ], 400);
        }
    }

    /**
     * Remove seal mark from a comment.
     */
    public function unmarkComment(SealMarkTypeRequest $request, Comment $comment): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->sealService->removeSealMark($user, $comment, $request->validated()['type']);

            return response()->json([
                'success' => true,
                'message' => __('seals.removed_successfully'),
                'available_seals' => $result['available_seals'],
                'comment' => [
                    'recommended_seals_count' => $comment->fresh()->recommended_seals_count,
                    'advise_against_seals_count' => $comment->fresh()->advise_against_seals_count,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ErrorHelper::getSafeMessage($e, __('seals.error_removing')),
            ], 400);
        }
    }

    /**
     * Get seal marks for a post.
     */
    public function getPostMarks(Request $request, Post $post): JsonResponse
    {
        $marks = $this->sealService->getSealMarksForContent($post);

        return response()->json($marks);
    }

    /**
     * Get seal marks for a comment.
     */
    public function getCommentMarks(Request $request, Comment $comment): JsonResponse
    {
        $marks = $this->sealService->getSealMarksForContent($comment);

        return response()->json($marks);
    }

    /**
     * Check if user has marked content.
     */
    public function checkUserMarks(CheckUserMarksRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validated();

        $content = $validated['content_type'] === 'post'
            ? Post::find($validated['content_id'])
            : Comment::find($validated['content_id']);

        if (! $content) {
            return response()->json(['error' => 'Content not found'], 404);
        }

        return response()->json([
            'has_recommended' => $this->sealService->hasUserMarked($user, $content, 'recommended'),
            'has_advise_against' => $this->sealService->hasUserMarked($user, $content, 'advise_against'),
        ]);
    }
}
