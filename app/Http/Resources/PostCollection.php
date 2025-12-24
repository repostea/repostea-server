<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\CursorPaginator;

final class PostCollection extends ResourceCollection
{
    /**
     * Disable default pagination wrapper to avoid duplicate meta values.
     */
    public static $wrap = null;

    /**
     * Transform the resource collection into an array.
     *
     * @param  Request  $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $resourceCollection = $this->resource->toArray();

        // Check if using cursor-based pagination
        if ($this->resource instanceof CursorPaginator) {
            return [
                'data' => $this->collection,
                'meta' => [
                    'post_count' => $this->count(),
                    'per_page' => $resourceCollection['per_page'] ?? count($this->collection),
                    'has_more' => $this->resource->hasMorePages(),
                    'next_cursor' => $this->resource->nextCursor()?->encode(),
                    'prev_cursor' => $this->resource->previousCursor()?->encode(),
                ],
            ];
        }

        // Default page-based pagination
        return [
            'data' => $this->collection,
            'meta' => [
                'post_count' => $this->count(),
                'total_posts' => $resourceCollection['total'] ?? $this->count(),
                'current_page' => $resourceCollection['current_page'] ?? 1,
                'per_page' => $resourceCollection['per_page'] ?? count($this->collection),
                'last_page' => $resourceCollection['last_page'] ?? 1,
            ],
        ];
    }

    /**
     * Customize the pagination information for the resource.
     * Return empty array to prevent Laravel from adding duplicate pagination info.
     */
    public function paginationInformation($request, $paginated, $default): array
    {
        return [];
    }
}
