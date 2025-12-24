<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SavedListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'user_id' => $this->resource->user_id,
            'username' => $this->resource->user?->username,
            'is_public' => $this->resource->is_public,
            'type' => $this->resource->type,
            'slug' => $this->resource->slug,
            'uuid' => $this->resource->uuid,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'posts_count' => $this->when(
                ! $request->routeIs('lists.posts'),
                $this->resource->posts_count ?? $this->whenLoaded('posts', fn () => $this->resource->posts->count(), 0),
            ),
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
