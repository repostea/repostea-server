<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CommentWithPostResource extends JsonResource
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
        if (! $this->resource->relationLoaded('post')) {
            $this->resource->load('post');
        }

        // Handle case where post was deleted
        if ($this->resource->post === null) {
            return [
                'id' => $this->resource->id,
                'content' => $this->resource->content,
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
                'votes_count' => $this->resource->votes_count,
                'post' => null,
            ];
        }

        return [
            'id' => $this->resource->id,
            'content' => $this->resource->content,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'votes_count' => $this->resource->votes_count,
            'post' => [
                'id' => $this->resource->post->id,
                'title' => $this->resource->post->title,
                'slug' => $this->resource->post->slug,
                'permalink' => "/p/{$this->resource->post->uuid}",
            ],
        ];
    }
}
