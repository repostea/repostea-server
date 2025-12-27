<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavedListPostRequest;
use App\Http\Requests\StoreSavedListRequest;
use App\Http\Requests\TogglePostRequest;
use App\Http\Requests\UpdateSavedListRequest;
use App\Http\Resources\PostCollection;
use App\Http\Resources\SavedListCollection;
use App\Http\Resources\SavedListResource;
use App\Http\Responses\ApiResponse;
use App\Models\Post;
use App\Models\SavedList;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class SavedListController extends Controller
{
    public function index(Request $request): SavedListCollection
    {
        $lists = Auth::user()->savedLists()->withCount('posts')->with('user')->get();

        return new SavedListCollection($lists);
    }

    public function store(StoreSavedListRequest $request): SavedListResource|JsonResponse
    {
        $validated = $request->validated();

        if (in_array($validated['type'], ['favorite', 'read_later'], true)) {
            $existingList = Auth::user()->savedLists()
                ->where('type', $validated['type'])
                ->where('user_id', Auth::id())
                ->first();

            if ($existingList) {
                return response()->json([
                    'message' => __('messages.savedlists.type_exists'),
                ], 422);
            }
        }

        $list = Auth::user()->savedLists()->create($validated);

        return new SavedListResource($list);
    }

    public function show($identifier): SavedListResource
    {
        $savedList = SavedList::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        Gate::authorize('view', $savedList);

        $savedList->load(['posts', 'user']);

        return new SavedListResource($savedList);
    }

    public function update(UpdateSavedListRequest $request, $identifier): SavedListResource|JsonResponse
    {
        $savedList = SavedList::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        Gate::authorize('update', $savedList);

        if (in_array($savedList->type, ['favorite', 'read_later'], true) && $request->has('type')) {
            return response()->json([
                'message' => __('messages.savedlists.cannot_change_special_type'),
            ], 422);
        }

        $savedList->update($request->validated());

        return new SavedListResource($savedList);
    }

    public function destroy($identifier): JsonResponse
    {
        $savedList = SavedList::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        Gate::authorize('delete', $savedList);

        if (in_array($savedList->type, ['favorite', 'read_later'], true)) {
            return response()->json([
                'message' => __('messages.savedlists.cannot_delete_special'),
            ], 422);
        }

        $savedList->delete();

        return ApiResponse::success(null, __('messages.savedlists.deleted'));
    }

    public function addPost(SavedListPostRequest $request, $identifier): JsonResponse
    {
        $savedList = SavedList::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        Gate::authorize('update', $savedList);

        $validated = $request->validated();

        if ($savedList->posts()->where('post_id', $validated['post_id'])->exists()) {
            return response()->json([
                'message' => __('messages.savedlists.post_already_in_list'),
            ], 422);
        }

        $savedList->posts()->attach($validated['post_id'], [
            'notes' => $validated['notes'],
        ]);

        return response()->json([
            'message' => __('messages.savedlists.post_added'),
        ]);
    }

    public function removePost(TogglePostRequest $request, $identifier): JsonResponse
    {
        $savedList = SavedList::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        Gate::authorize('update', $savedList);

        $savedList->posts()->detach($request->validated()['post_id']);

        return response()->json([
            'message' => __('messages.savedlists.post_removed'),
        ]);
    }

    public function posts($identifier): PostCollection
    {
        $savedList = SavedList::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        Gate::authorize('view', $savedList);

        $userId = Auth::id();

        $posts = $savedList->posts()
            ->with(['user'])
            ->when($userId, fn ($q) => $q->with(['votes' => fn ($q) => $q->where('user_id', $userId)]))
            ->paginate(15);

        if ($userId) {
            foreach ($posts as $post) {
                /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Vote> $votesRelation */
                $votesRelation = $post->getRelation('votes');
                $userVote = $votesRelation->first();
                $post->user_vote = $userVote?->value;
                $post->user_vote_type = $userVote?->type;
            }
        }

        return new PostCollection($posts);
    }

    public function toggleFavorite(TogglePostRequest $request): JsonResponse
    {
        $user = Auth::user();
        $favoritesList = $user->favorites_list;
        $postId = $request->validated()['post_id'];

        if ($user->hasFavorite($postId)) {
            $favoritesList->posts()->detach($postId);

            return response()->json([
                'message' => __('messages.savedlists.removed_from_favorites'),
                'is_favorite' => false,
            ]);
        }
        $favoritesList->posts()->attach($postId);

        return response()->json([
            'message' => __('messages.savedlists.added_to_favorites'),
            'is_favorite' => true,
        ]);
    }

    public function toggleReadLater(TogglePostRequest $request): JsonResponse
    {
        $user = Auth::user();
        $readLaterList = $user->read_later_list;
        $postId = $request->validated()['post_id'];

        if ($user->hasReadLater($postId)) {
            $readLaterList->posts()->detach($postId);

            return response()->json([
                'message' => __('messages.savedlists.removed_from_read_later'),
                'is_read_later' => false,
            ]);
        }
        $readLaterList->posts()->attach($postId);

        return response()->json([
            'message' => __('messages.savedlists.added_to_read_later'),
            'is_read_later' => true,
        ]);
    }

    public function checkSavedStatus(Post $post): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'is_favorite' => $user->hasFavorite($post->id),
            'is_read_later' => $user->hasReadLater($post->id),
            'saved_lists' => $user->savedLists()
                ->whereHas('posts', static function ($query) use ($post): void {
                    $query->where('post_id', $post->id);
                })
                ->where('type', 'custom')
                ->get(['id', 'name', 'uuid', 'slug']),
        ]);
    }

    public function updatePostNotes(SavedListPostRequest $request, $identifier): JsonResponse
    {
        $savedList = SavedList::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        Gate::authorize('update', $savedList);

        $validated = $request->validated();

        $pivotRecord = $savedList->posts()->where('post_id', $validated['post_id'])->first();

        if (! $pivotRecord) {
            return response()->json([
                'message' => __('messages.savedlists.post_not_in_list'),
            ], 404);
        }

        $savedList->posts()->updateExistingPivot($validated['post_id'], [
            'notes' => $validated['notes'],
        ]);

        return response()->json([
            'message' => __('messages.savedlists.notes_updated'),
        ]);
    }

    public function clearList($identifier): JsonResponse
    {
        $savedList = SavedList::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        Gate::authorize('update', $savedList);

        if (in_array($savedList->type, ['favorite', 'read_later'], true)) {
            return response()->json([
                'message' => __('messages.savedlists.cannot_clear_special'),
            ], 422);
        }

        $savedList->posts()->detach();

        return response()->json([
            'message' => __('messages.savedlists.cleared'),
        ]);
    }

    // Method for username/slug format (read-only, for SEO-friendly URLs)
    public function showByUsernameAndSlug(string $username, string $slug): SavedListResource
    {
        $user = User::where('username', $username)->firstOrFail();

        $savedList = SavedList::where('user_id', $user->id)
            ->where('slug', $slug)
            ->firstOrFail();

        Gate::authorize('view', $savedList);
        $savedList->load(['posts', 'user']);

        return new SavedListResource($savedList);
    }
}
