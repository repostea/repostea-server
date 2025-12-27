<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\KarmaLevel;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SyncController extends Controller
{
    public function getLastUpdated(): JsonResponse
    {
        return response()->json([
            'posts' => Post::max('updated_at'),
            'karma_levels' => KarmaLevel::max('updated_at'),
        ]);
    }

    public function syncPosts(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'last_sync' => 'required|date',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $lastSync = $validated['last_sync'];
        $limit = $validated['limit'] ?? 50;

        $posts = Post::where('updated_at', '>', $lastSync)
            ->with(['user'])
            ->paginate($limit);

        return PostResource::collection($posts);
    }
}
