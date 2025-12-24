<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TagResource extends JsonResource
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
            'name_key' => $this->resource->name_key,
            'name' => __('tags.' . $this->resource->name_key),
            'slug' => $this->resource->slug,
            'description_key' => $this->resource->description_key,
            'description' => $this->resource->description_key ? __('tags.descriptions.' . $this->resource->description_key) : null,
            'category_id' => $this->resource->tag_category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->resource->category->id,
                'name_key' => $this->resource->category->name_key,
                'name' => __('tags.' . $this->resource->category->name_key),
                'slug' => $this->resource->category->slug,
            ]),
            'posts_count' => $this->whenCounted('posts'),
            'entries_count' => $this->whenCounted('posts'),
        ];
    }
}
