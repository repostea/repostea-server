<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isExternalImport = (bool) ($this->resource->source !== null && $this->resource->source !== '');

        // Check if user is deleted (soft deleted)
        // For anonymous posts, don't include user data to protect privacy
        $user = null;
        if (! $this->resource->is_anonymous && $this->resource->relationLoaded('user') && $this->resource->user) {
            // Only include user if not deleted
            if (! $this->resource->user->deleted_at) {
                $user = new UserResource($this->resource->user);
            }
        }

        return [
            'id' => $this->resource->id,
            'uuid' => $this->resource->uuid,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'content' => $this->resource->content,
            'url' => $this->resource->url,
            'thumbnail_url' => $this->resolveThumbnailUrl(),
            'user' => $user,
            'sub' => $this->when($this->resource->relationLoaded('sub'), function () {
                if (! $this->resource->sub) {
                    return;
                }

                return [
                    'id' => $this->resource->sub->id,
                    'name' => $this->resource->sub->name,
                    'display_name' => $this->resource->sub->display_name,
                    'icon' => $this->resource->sub->icon,
                    'members_count' => $this->resource->sub->members_count ?? 0,
                    'posts_count' => $this->resource->sub->posts_count ?? 0,
                ];
            }),
            'author' => $this->resource->is_anonymous ? null : $this->resource->getDisplayUsername(),
            'is_original' => (bool) $this->resource->is_original,
            'is_anonymous' => (bool) $this->resource->is_anonymous,
            'is_nsfw' => (bool) $this->resource->is_nsfw,
            'language_locked_by_admin' => (bool) $this->resource->language_locked_by_admin,
            'nsfw_locked_by_admin' => (bool) $this->resource->nsfw_locked_by_admin,
            'moderated_by' => $this->when($request->user()?->isAdmin() || $request->user()?->isModerator(), $this->resource->moderated_by),
            'status' => $this->resource->status,
            'frontpage_at' => $this->resource->frontpage_at,
            'published_at' => $this->resource->published_at,
            'vote_count' => $this->resource->votes_count,
            'comment_count' => $this->resource->comment_count,
            'comments_open' => $this->areCommentsOpen(),
            'voting_open' => $this->isVotingOpen(),
            'views' => $this->resource->views,
            'total_views' => $this->resource->total_views ?? 0,
            'impressions' => $this->resource->impressions ?? 0,

            // Federation stats (from fediverse interactions)
            'federation' => $this->getFederationData($request),

            'recommended_seals_count' => $this->resource->recommended_seals_count ?? 0,
            'advise_against_seals_count' => $this->resource->advise_against_seals_count ?? 0,
            'user_has_recommended' => $this->when($request->user() && isset($this->resource->user_has_recommended), $this->resource->user_has_recommended),
            'user_has_advise_against' => $this->when($request->user() && isset($this->resource->user_has_advise_against), $this->resource->user_has_advise_against),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'language_code' => $this->resource->language_code,
            'user_vote' => $this->when(isset($this->resource->user_vote), $this->resource->user_vote),
            'user_vote_type' => $this->when(isset($this->resource->user_vote_type), $this->resource->user_vote_type),
            'is_visited' => $this->when(isset($this->resource->is_visited), $this->resource->is_visited),
            'last_visited_at' => $this->when(isset($this->resource->last_visited_at), $this->resource->last_visited_at),
            'new_comments_count' => $this->when(isset($this->resource->new_comments_count), $this->resource->new_comments_count),
            'source' => $this->resource->source,
            'source_name' => $this->resource->source_name,
            'source_url' => $this->resource->source_url,
            'is_external_import' => $isExternalImport,
            'permalink' => "/p/{$this->resource->uuid}",
            'content_type' => $this->resource->content_type ?? 'link',
            'media_provider' => $this->resource->media_provider,
            'media_metadata' => is_string($this->resource->media_metadata)
                ? json_decode($this->resource->media_metadata, true)
                : $this->resource->media_metadata,
            'is_media' => $this->resource->isMediaContent(),
            'formatted_media_provider' => $this->when($this->resource->media_provider, $this->resource->getFormattedMediaProvider()),
            'tags' => TagResource::collection($this->whenLoaded('tags')),

            // Matched comment for search results
            'matched_comment' => $this->when(
                $this->resource->relationLoaded('comments') && $this->resource->comments->isNotEmpty(),
                function () {
                    $comment = $this->resource->comments->first();

                    return [
                        'id' => $comment->id,
                        'content' => $comment->content,
                        'user' => $comment->user ? ['username' => $comment->user->username] : null,
                        'created_at' => $comment->created_at,
                    ];
                },
            ),

            // Reports info - count visible to all users for transparency
            'reports_count' => $this->resource->reports_count ?? 0,

            // Relationships count (divided by 2 because relationships are bidirectional)
            'relationships_count' => (int) ((($this->resource->relationships_as_source_count ?? 0) + ($this->resource->relationships_as_target_count ?? 0)) / 2),

            // Check if current user can edit this post
            'can_edit' => $this->when($request->user(), fn () => $request->user()->id === $this->resource->user_id),

            'reports' => $this->when(
                $request->user() && $this->resource->relationLoaded('reports'),
                function () use ($request) {
                    $user = $request->user();

                    return $this->resource->reports->map(fn ($report) => [
                        'id' => $report->id,
                        'reported_by' => $report->reported_by,
                        'reason' => $report->reason,
                        'description' => $report->description,
                        'created_at' => $report->created_at,
                        'status' => $report->status,
                        'is_own' => $report->reported_by === $user->id,
                    ]);
                },
            ),
        ];
    }

    /**
     * Get federation data for the post.
     *
     * @return array<string, mixed>
     */
    private function getFederationData(Request $request): array
    {
        $isOwner = $request->user() && $request->user()->id === $this->resource->user_id;
        $settings = $this->resource->activityPubSettings;

        $data = [
            'likes_count' => $this->resource->federation_likes_count ?? 0,
            'shares_count' => $this->resource->federation_shares_count ?? 0,
            'replies_count' => $this->resource->federation_replies_count ?? 0,
            'has_engagement' => ($this->resource->federation_likes_count ?? 0) > 0
                || ($this->resource->federation_shares_count ?? 0) > 0
                || ($this->resource->federation_replies_count ?? 0) > 0,
        ];

        // Only show editable federation status to post owner
        if ($isOwner) {
            $data['should_federate'] = $settings?->should_federate ?? false;
            $data['is_federated'] = $settings?->is_federated ?? false;
            $data['federated_at'] = $settings?->federated_at;
        }

        return $data;
    }

    /**
     * Check if comments are still open for this post.
     */
    private function areCommentsOpen(): bool
    {
        $maxAgeDays = (int) config('posts.commenting_max_age_days', 0);

        if ($maxAgeDays === 0) {
            return true;
        }

        return (int) $this->resource->created_at->diffInDays(now()) <= $maxAgeDays;
    }

    /**
     * Check if voting is still open for this post.
     */
    private function isVotingOpen(): bool
    {
        $maxAgeDays = (int) config('posts.voting_max_age_days', 7);

        if ($maxAgeDays === 0) {
            return true;
        }

        return (int) $this->resource->created_at->diffInDays(now()) <= $maxAgeDays;
    }

    /**
     * Get thumbnail URL from images table.
     */
    private function resolveThumbnailUrl(): ?string
    {
        if ($this->resource->thumbnail_image_id && $this->resource->thumbnailImage) {
            return $this->resource->thumbnailImage->getUrl();
        }

        return null;
    }
}
