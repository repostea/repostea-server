<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagCollection;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class TagController extends Controller
{
    public function index(Request $request): mixed
    {
        $query = Tag::query();

        $cacheKey = 'tags:' . md5(json_encode($request->all()));

        return Cache::tags(['tags'])->remember($cacheKey, now()->addDay(), static function () use ($request, $query) {
            if ($request->has('category_id')) {
                $categoryId = $request->category_id;
                if ($categoryId === 'null') {
                    $query->whereNull('tag_category_id');
                } else {
                    $query->where('tag_category_id', $categoryId);
                }
            }

            if ($request->has('content_type')) {
                $contentType = $request->content_type;
                // Assuming a relationship exists between tags and posts
                $query->whereHas('posts', static function ($q) use ($contentType): void {
                    $q->where('content_type', $contentType);
                });
            }

            // Assuming a relationship exists between tags and categories
            $query->with('category');

            $perPage = $request->input('per_page', 100);
            if ($perPage === 'all') {
                $tags = $query->get();

                return TagResource::collection($tags);
            }
            $tags = $query->paginate($perPage);

            return new TagCollection($tags);
        });
    }

    public function show($nameOrSlug): mixed
    {
        $cacheKey = 'tag:' . $nameOrSlug;

        return Cache::tags(['tags'])->remember($cacheKey, now()->addDay(), static function () use ($nameOrSlug) {
            $tag = Tag::where('name_key', $nameOrSlug)
                ->orWhere('slug', $nameOrSlug)
                ->with('category')
                ->firstOrFail();

            return new TagResource($tag);
        });
    }

    public function showById($id): mixed
    {
        $cacheKey = 'tag:id:' . $id;

        return Cache::tags(['tags'])->remember($cacheKey, now()->addDay(), static function () use ($id) {
            $tag = Tag::with('category')->findOrFail($id);

            return new TagResource($tag);
        });
    }

    public function getTagsByCategory($categoryId): mixed
    {
        $cacheKey = 'tags:category:' . $categoryId;

        return Cache::tags(['tags'])->remember($cacheKey, now()->addDay(), static function () use ($categoryId) {
            $tags = Tag::where('tag_category_id', $categoryId)
                ->with('category')
                ->get();

            return TagResource::collection($tags);
        });
    }

    public function getTagCategories(): mixed
    {
        return Cache::tags(['tags'])->remember('tag_categories_with_tags', now()->addDay(), static function () {
            $categories = TagCategory::with('tags')->get();

            return response()->json([
                'data' => $categories->map(static fn ($category) => [
                    'id' => $category->id,
                    'name_key' => $category->name_key,
                    'name' => __('tags.' . $category->name_key),
                    'slug' => $category->slug,
                    'icon' => $category->icon,
                    'tags' => TagResource::collection($category->tags),
                ]),
            ]);
        });
    }
}
