<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AgoraMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'user' => $this->when(! $this->resource->is_anonymous, new UserResource($this->resource->user)),
            'parent_id' => $this->resource->parent_id,
            'root_id' => $this->resource->root_id,
            'parent' => $this->when($this->resource->relationLoaded('parent') && $this->resource->parent, [
                'id' => $this->resource->parent?->id,
                'content' => truncate_content($this->resource->parent?->content ?? '', 100),
                'user' => $this->when(! $this->resource->parent?->is_anonymous, new UserResource($this->resource->parent?->user)),
                'is_anonymous' => (bool) $this->resource->parent?->is_anonymous,
            ]),
            'content' => $this->resource->content,
            'votes_count' => $this->resource->votes_count,
            'replies_count' => $this->resource->replies_count,
            'total_replies_count' => $this->resource->total_replies_count ?? 0,
            'is_anonymous' => (bool) $this->resource->is_anonymous,
            'language_code' => $this->resource->language_code,
            'created_at' => $this->resource->created_at,
            'edited_at' => $this->resource->edited_at,
            'expires_in_hours' => $this->resource->expires_in_hours,
            'expiry_mode' => $this->resource->expiry_mode,
            'expires_at' => $this->resource->expires_at,
            'user_vote' => $this->when(isset($this->resource->user_vote), $this->resource->user_vote),
            'user_vote_type' => $this->when(isset($this->resource->user_vote_type), $this->resource->user_vote_type),
            'vote_type_summary' => $this->when(method_exists($this->resource, 'getVoteTypeSummary'), fn () => $this->resource->getVoteTypeSummary()),
            'replies' => self::collection($this->whenLoaded('replies')),
        ];
    }
}
