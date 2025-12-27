<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

final class CommentResource extends JsonResource
{
    /**
     * @param  Request  $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $status = $this->resource->status ?? Comment::STATUS_PUBLISHED;

        // Don't send actual content if comment is hidden or deleted
        // Frontend will show appropriate message based on status
        $content = $this->resource->content;
        if ($status === Comment::STATUS_HIDDEN) {
            $content = '[hidden by moderation]';
        } elseif ($status === Comment::STATUS_DELETED_BY_AUTHOR) {
            $content = '[deleted]';
        }

        // For anonymous comments, don't include user data to protect privacy
        $user = null;
        if (! $this->resource->is_anonymous && $this->resource->relationLoaded('user') && $this->resource->user) {
            $user = new UserResource($this->resource->user);
        }

        // Check if this is a federated/remote comment
        $isRemote = $this->resource->remote_user_id !== null;
        $remoteUser = null;
        if ($isRemote && $this->resource->relationLoaded('remoteUser') && $this->resource->remoteUser) {
            $remoteUser = [
                'id' => $this->resource->remoteUser->id,
                'username' => $this->resource->remoteUser->username,
                'domain' => $this->resource->remoteUser->domain,
                'handle' => $this->resource->remoteUser->handle,
                'display_name' => $this->resource->remoteUser->display_name,
                'avatar_url' => $this->resource->remoteUser->avatar_url,
                'profile_url' => $this->resource->remoteUser->profile_url,
                'software' => $this->resource->remoteUser->software,
            ];
        }

        return [
            'id' => $this->resource->id,
            'content' => $content,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'edited_at' => $this->resource->edited_at,
            'status' => $status,
            'moderated_by' => $this->when($request->user()?->isAdmin() || $request->user()?->isModerator(), $this->resource->moderated_by),
            'moderation_reason' => $this->when($status === Comment::STATUS_HIDDEN, $this->resource->moderation_reason),
            'moderated_at' => $this->resource->moderated_at,
            'user' => $user,
            'is_anonymous' => (bool) $this->resource->is_anonymous,

            // Federation/remote user data
            'is_remote' => $isRemote,
            'remote_user' => $remoteUser,
            'source' => $this->resource->source ?? 'local',
            'source_uri' => $this->when($isRemote, $this->resource->source_uri),

            'post_id' => $this->resource->post_id,
            'post' => $this->when($this->resource->relationLoaded('post'), fn () => [
                'id' => $this->resource->post->id,
                'slug' => $this->resource->post->slug,
                'title' => $this->resource->post->title,
            ]),
            'parent_id' => $this->resource->parent_id,
            'votes' => $this->resource->votes_count,
            'recommended_seals_count' => $this->resource->recommended_seals_count ?? 0,
            'advise_against_seals_count' => $this->resource->advise_against_seals_count ?? 0,
            'user_has_recommended' => $this->when($request->user() && isset($this->resource->user_has_recommended), $this->resource->user_has_recommended),
            'user_has_advise_against' => $this->when($request->user() && isset($this->resource->user_has_advise_against), $this->resource->user_has_advise_against),
            'user_vote' => $this->when(Auth::check(), $this->resource->user_vote),
            'user_vote_type' => $this->when(Auth::check(), $this->resource->user_vote_type),
            'vote_stats' => $this->when($this->resource->relationLoaded('votes'), new VoteStatsResource($this->resource)),
            'replies' => self::collection($this->whenLoaded('replies')),
        ];
    }
}
