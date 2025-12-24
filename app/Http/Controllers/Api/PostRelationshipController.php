<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostRelationship;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Log;

final class PostRelationshipController extends Controller
{
    /**
     * Get all relationships for a post, grouped by category.
     */
    public function index(Post $post, Request $request): JsonResponse
    {
        // Sort by score DESC (higher score first), then by created_at ASC (older first) for ties
        $relationships = $post->allRelationships()
            ->sortByDesc('score')
            ->sortBy('created_at');
        $user = $request->user();

        // Group by related post and relationship type to avoid duplicates
        $uniqueRelationships = $relationships->unique(function ($relationship) use ($post) {
            $isSource = $relationship->source_post_id === $post->id;
            $relatedPostId = $isSource ? $relationship->target_post_id : $relationship->source_post_id;

            return $relatedPostId . '-' . $relationship->relationship_type;
        });

        // Separate into two categories
        $ownRelationships = [];
        $externalRelationships = [];

        // Pre-load all user votes in a single query (avoid N+1)
        $userVotesMap = collect();
        if ($user) {
            $relationshipIds = $uniqueRelationships->pluck('id');
            $userVotesMap = \App\Models\RelationshipVote::where('user_id', $user->id)
                ->whereIn('relationship_id', $relationshipIds)
                ->pluck('vote', 'relationship_id');
        }

        foreach ($uniqueRelationships as $relationship) {
            $isSource = $relationship->source_post_id === $post->id;
            $relatedPost = $isSource ? $relationship->targetPost : $relationship->sourcePost;

            // Get user's vote from pre-loaded map
            $userVote = $userVotesMap[$relationship->id] ?? null;

            $relationData = [
                'id' => $relationship->id,
                'type' => $relationship->relationship_type,
                'category' => $relationship->relation_category ?? PostRelationship::getCategoryForType($relationship->relationship_type),
                'direction' => $isSource ? 'outgoing' : 'incoming',
                'post' => [
                    'id' => $relatedPost->id,
                    'title' => $relatedPost->title,
                    'slug' => $relatedPost->slug,
                    'uuid' => $relatedPost->uuid,
                    'content' => \Illuminate\Support\Str::limit($relatedPost->content ?? '', 200),
                    'created_at' => $relatedPost->created_at,
                    'frontpage_at' => $relatedPost->frontpage_at,
                    'vote_count' => $relatedPost->votes_count ?? 0,
                    'comment_count' => $relatedPost->comment_count ?? 0,
                    'author' => $relatedPost->getDisplayUsername(),
                ],
                'notes' => $relationship->notes,
                'created_by' => $relationship->is_anonymous ? null : ($relationship->creator->username ?? null),
                'is_anonymous' => $relationship->is_anonymous ?? false,
                'is_owner' => $user && $relationship->created_by === $user->id,
                'created_at' => $relationship->created_at,
                'upvotes_count' => $relationship->upvotes_count,
                'downvotes_count' => $relationship->downvotes_count,
                'score' => $relationship->score,
                'user_vote' => $userVote,
            ];

            // Categorize based on relation_category
            $category = $relationship->relation_category ?? PostRelationship::getCategoryForType($relationship->relationship_type);
            if ($category === PostRelationship::CATEGORY_OWN) {
                $ownRelationships[] = $relationData;
            } else {
                $externalRelationships[] = $relationData;
            }
        }

        return response()->json([
            'data' => [
                'own' => $ownRelationships,
                'external' => $externalRelationships,
            ],
        ]);
    }

    /**
     * Create a new relationship between posts.
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        $validated = $request->validate([
            'target_post_id' => ['required', 'exists:posts,id'],
            'relationship_type' => ['required', Rule::in(PostRelationship::getRelationshipTypes())],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);

        // Check if user is authenticated
        if (! Auth::check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $targetPost = Post::findOrFail($validated['target_post_id']);

        // Prevent self-relationship
        if ($post->id === $targetPost->id) {
            return response()->json([
                'message' => __('relationships.errors.self_relation'),
            ], 422);
        }

        // Check if relationship type requires author permission
        $relationshipType = $validated['relationship_type'];

        // Determine the category based on the relationship type
        $relationCategory = PostRelationship::getCategoryForType($relationshipType);

        // OWN CONTENT validations (continuation, correction)
        if ($relationCategory === PostRelationship::CATEGORY_OWN) {
            // Only the author of the CURRENT post can create own content relations
            if ($post->user_id !== Auth::id()) {
                return response()->json([
                    'message' => __('relationships.errors.only_author_can_create'),
                ], 403);
            }
        }

        // EXTERNAL CONTENT validations (update, reply, related, duplicate)
        if ($relationCategory === PostRelationship::CATEGORY_EXTERNAL) {
            // Reply: cannot reply to your own post
            if ($relationshipType === PostRelationship::TYPE_REPLY) {
                if ($targetPost->user_id === Auth::id()) {
                    return response()->json([
                        'message' => __('relationships.errors.cannot_reply_own_post'),
                    ], 403);
                }
            }
        }

        // Check if relationship already exists
        $exists = PostRelationship::where('source_post_id', $post->id)
            ->where('target_post_id', $targetPost->id)
            ->where('relationship_type', $validated['relationship_type'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => __('relationships.errors.already_exists'),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create the relationship
            $relationship = PostRelationship::create([
                'source_post_id' => $post->id,
                'target_post_id' => $targetPost->id,
                'relationship_type' => $validated['relationship_type'],
                'relation_category' => $relationCategory,
                'created_by' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
                'is_anonymous' => $validated['is_anonymous'] ?? false,
            ]);

            // Only create bidirectional relationship for certain types
            // Continuation, correction, and update are UNIDIRECTIONAL (A continues from B, not both ways)
            $unidirectionalTypes = [
                PostRelationship::TYPE_CONTINUATION,
                PostRelationship::TYPE_CORRECTION,
                PostRelationship::TYPE_UPDATE,
            ];

            if (! in_array($validated['relationship_type'], $unidirectionalTypes)) {
                // Create bidirectional relationship (inverse) for related, duplicate, reply
                PostRelationship::create([
                    'source_post_id' => $targetPost->id,
                    'target_post_id' => $post->id,
                    'relationship_type' => $validated['relationship_type'],
                    'relation_category' => $relationCategory,
                    'created_by' => Auth::id(),
                    'notes' => $validated['notes'] ?? null,
                    'is_anonymous' => $validated['is_anonymous'] ?? false,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => __('relationships.success.created'),
                'data' => $relationship->load(['sourcePost', 'targetPost', 'creator']),
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => __('relationships.errors.create_failed'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Delete a relationship.
     */
    public function destroy(Post $post, PostRelationship $relationship): JsonResponse
    {
        // Check if user is authenticated
        if (! Auth::check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Verify the relationship belongs to the post
        if ($relationship->source_post_id !== $post->id && $relationship->target_post_id !== $post->id) {
            return response()->json(['message' => __('relationships.errors.not_found')], 404);
        }

        // Only the creator or post author can delete
        if ($relationship->created_by !== Auth::id() && $post->user_id !== Auth::id()) {
            return response()->json([
                'message' => __('relationships.errors.no_permission'),
            ], 403);
        }

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
                'message' => __('relationships.success.deleted'),
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => __('relationships.errors.delete_failed'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get continuation chain for breadcrumbs.
     */
    public function continuationChain(Post $post): JsonResponse
    {
        try {
            $chain = $post->getContinuationChain();

            return response()->json([
                'data' => collect($chain)->map(fn ($chainPost) => [
                    'id' => $chainPost->id,
                    'title' => $chainPost->title,
                    'slug' => $chainPost->slug,
                    'url' => $chainPost->slug ? "/posts/{$chainPost->slug}" : null,
                ]),
            ]);
        } catch (Exception $e) {
            Log::error('Error in continuationChain: ' . $e->getMessage());

            return response()->json(['data' => []], 500);
        }
    }

    /**
     * Get available relationship types grouped by category.
     */
    public function types(Request $request): JsonResponse
    {
        // Get locale from request parameter or use default
        $locale = $request->input('locale', app()->getLocale());
        app()->setLocale($locale);

        $ownTypes = collect(PostRelationship::OWN_CONTENT_TYPES)->map(fn ($type) => [
            'value' => $type,
            'label' => __("relationships.types.{$type}"),
            'category' => PostRelationship::CATEGORY_OWN,
            'requires_author' => true,
            'icon' => $this->getTypeIcon($type),
            'description' => __("relationships.descriptions.{$type}"),
        ]);

        $externalTypes = collect(PostRelationship::EXTERNAL_CONTENT_TYPES)->map(fn ($type) => [
            'value' => $type,
            'label' => __("relationships.types.{$type}"),
            'category' => PostRelationship::CATEGORY_EXTERNAL,
            'requires_author' => false,
            'icon' => $this->getTypeIcon($type),
            'description' => __("relationships.descriptions.{$type}"),
        ]);

        return response()->json([
            'data' => [
                'own' => $ownTypes,
                'external' => $externalTypes,
            ],
        ]);
    }

    /**
     * Get icon for relationship type.
     */
    private function getTypeIcon(string $type): string
    {
        return match ($type) {
            PostRelationship::TYPE_REPLY => 'reply',
            PostRelationship::TYPE_CONTINUATION => 'arrow-right',
            PostRelationship::TYPE_RELATED => 'link',
            PostRelationship::TYPE_UPDATE => 'arrow-rotate-right',
            PostRelationship::TYPE_CORRECTION => 'pen-to-square',
            PostRelationship::TYPE_DUPLICATE => 'clone',
            default => 'link',
        };
    }

    /**
     * Get description for relationship type.
     */
}
