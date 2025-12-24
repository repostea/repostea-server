<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostRelationship;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminPostRelationshipController extends Controller
{
    /**
     * Get all post relationships with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $type = $request->input('type');
        $postId = $request->input('post_id');
        $search = $request->input('search');

        $query = PostRelationship::with(['sourcePost', 'targetPost', 'creator'])
            ->orderBy('created_at', 'desc');

        // Filter by relationship type
        if ($type) {
            $query->where('relationship_type', $type);
        }

        // Filter by post ID (either source or target)
        if ($postId) {
            $query->where(function ($q) use ($postId): void {
                $q->where('source_post_id', $postId)
                    ->orWhere('target_post_id', $postId);
            });
        }

        // Search in post titles
        if ($search) {
            $query->whereHas('sourcePost', function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%");
            })->orWhereHas('targetPost', function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%");
            });
        }

        $relationships = $query->paginate($perPage);

        return response()->json([
            'data' => $relationships->items(),
            'meta' => [
                'current_page' => $relationships->currentPage(),
                'last_page' => $relationships->lastPage(),
                'per_page' => $relationships->perPage(),
                'total' => $relationships->total(),
            ],
        ]);
    }

    /**
     * Get relationship statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_relationships' => PostRelationship::count(),
            'by_type' => PostRelationship::select('relationship_type', DB::raw('count(*) as count'))
                ->groupBy('relationship_type')
                ->get()
                ->mapWithKeys(fn ($item) => [$item->relationship_type => $item->count]),
            'recent_relationships' => PostRelationship::where('created_at', '>=', now()->subDays(7))->count(),
            'posts_with_relationships' => DB::table('post_relationships')
                ->select('source_post_id')
                ->union(
                    DB::table('post_relationships')->select('target_post_id'),
                )
                ->distinct()
                ->count(),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Force delete a relationship (admin override).
     */
    public function destroy(PostRelationship $relationship): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Delete bidirectional relationship
            PostRelationship::where([
                ['source_post_id', '=', $relationship->target_post_id],
                ['target_post_id', '=', $relationship->source_post_id],
                ['relationship_type', '=', $relationship->relationship_type],
            ])->delete();

            // Delete the main relationship
            $relationship->delete();

            DB::commit();

            return response()->json([
                'message' => 'Relationship deleted successfully',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete relationship',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Bulk delete relationships.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'relationship_ids' => ['required', 'array'],
            'relationship_ids.*' => ['required', 'exists:post_relationships,id'],
        ]);

        try {
            DB::beginTransaction();

            $count = 0;
            foreach ($validated['relationship_ids'] as $relationshipId) {
                $relationship = PostRelationship::find($relationshipId);
                if ($relationship) {
                    // Delete bidirectional relationship
                    PostRelationship::where([
                        ['source_post_id', '=', $relationship->target_post_id],
                        ['target_post_id', '=', $relationship->source_post_id],
                        ['relationship_type', '=', $relationship->relationship_type],
                    ])->delete();

                    $relationship->delete();
                    $count++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully deleted {$count} relationships",
                'count' => $count,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to bulk delete relationships',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get orphaned or problematic relationships.
     */
    public function audit(): JsonResponse
    {
        $issues = [];

        // Find relationships with deleted posts
        $withDeletedPosts = PostRelationship::whereDoesntHave('sourcePost')
            ->orWhereDoesntHave('targetPost')
            ->with(['sourcePost', 'targetPost', 'creator'])
            ->get();

        if ($withDeletedPosts->count() > 0) {
            $issues['deleted_posts'] = [
                'count' => $withDeletedPosts->count(),
                'relationships' => $withDeletedPosts,
            ];
        }

        // Find unidirectional relationships (should be bidirectional)
        $allRelationships = PostRelationship::all();
        $unidirectional = [];

        foreach ($allRelationships as $rel) {
            $inverse = PostRelationship::where([
                ['source_post_id', '=', $rel->target_post_id],
                ['target_post_id', '=', $rel->source_post_id],
                ['relationship_type', '=', $rel->relationship_type],
            ])->first();

            if (! $inverse) {
                $unidirectional[] = $rel;
            }
        }

        if (count($unidirectional) > 0) {
            $issues['unidirectional'] = [
                'count' => count($unidirectional),
                'relationships' => $unidirectional,
            ];
        }

        return response()->json([
            'data' => $issues,
            'has_issues' => count($issues) > 0,
        ]);
    }

    /**
     * Fix orphaned relationships.
     */
    public function cleanup(): JsonResponse
    {
        try {
            DB::beginTransaction();

            $deletedCount = 0;

            // Delete relationships with non-existent posts
            $orphaned = PostRelationship::whereDoesntHave('sourcePost')
                ->orWhereDoesntHave('targetPost')
                ->get();

            foreach ($orphaned as $relationship) {
                $relationship->delete();
                $deletedCount++;
            }

            DB::commit();

            return response()->json([
                'message' => 'Cleanup completed successfully',
                'deleted_count' => $deletedCount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Cleanup failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
