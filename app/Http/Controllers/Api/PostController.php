<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportPostRequest;
use App\Http\Requests\PostRequest;
use App\Http\Requests\SearchPostRequest;
use App\Http\Requests\UpdatePostStatusRequest;
use App\Http\Requests\VotePostRequest;
use App\Http\Resources\PostCollection;
use App\Http\Resources\PostResource;
use App\Http\Responses\ApiResponse;
use App\Models\Post;
use App\Models\PostView;
use App\Services\PostService;
use App\Services\PostVoteService;
use App\Services\ViewService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Log;

final class PostController extends Controller
{
    protected PostService $postService;

    protected PostVoteService $voteService;

    public function __construct(PostService $postService, PostVoteService $voteService)
    {
        $this->postService = $postService;
        $this->voteService = $voteService;
    }

    public function index(Request $request): PostCollection
    {
        $posts = $this->postService->getPosts($request);

        return new PostCollection($posts);
    }

    public function search(SearchPostRequest $request): PostCollection
    {
        $posts = $this->postService->getPosts($request);

        return new PostCollection($posts);
    }

    public function getFrontpage(Request $request): PostCollection
    {
        $posts = $this->postService->getFrontpage($request);

        return new PostCollection($posts);
    }

    public function getPending(Request $request): PostCollection
    {
        $posts = $this->postService->getPending($request);

        return new PostCollection($posts);
    }

    public function getPendingCount(Request $request): JsonResponse
    {
        $hours = $request->integer('hours', 24);
        $userId = Auth::id();
        $count = $this->postService->getPendingCount($hours, $userId);

        return response()->json(['count' => $count]);
    }

    public function getByContentType(string $contentType, Request $request): PostCollection
    {
        $request->merge(['content_type' => $contentType]);

        return $this->index($request);
    }

    public function showBySlug(string $slug): PostResource|JsonResponse
    {
        $post = $this->postService->getBySlug($slug);

        if (! $post) {
            return response()->json([
                'message' => __('posts.removed_or_not_found'),
                'hidden' => true,
            ], 404);
        }

        return new PostResource($post);
    }

    public function showByUuid(string $uuid): PostResource|JsonResponse
    {
        $post = $this->postService->getByUuid($uuid);

        if (! $post) {
            return response()->json([
                'message' => __('posts.removed_or_not_found'),
                'hidden' => true,
            ], 404);
        }

        return new PostResource($post);
    }

    public function show(Post $post): PostResource
    {
        $post = $this->postService->getById($post->id);

        return new PostResource($post);
    }

    public function store(PostRequest $request): PostResource
    {
        $post = $this->postService->createPost($request->validated());

        return new PostResource($post);
    }

    public function import(ImportPostRequest $request): PostResource|JsonResponse
    {
        try {
            $post = $this->postService->importPost($request->validated());

            return new PostResource($post);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.posts.import_error'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    public function update(PostRequest $request, Post $post): PostResource|JsonResponse
    {
        Gate::authorize('update', $post);

        try {
            $post = $this->postService->updatePost($post, $request->validated());

            return new PostResource($post);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.posts.update_error'),
                'error' => ErrorHelper::getSafeError($e),
            ], 422);
        }
    }

    public function destroy(Post $post): JsonResponse
    {
        Gate::authorize('delete', $post);
        $this->postService->deletePost($post);

        return ApiResponse::success(null, __('messages.posts.deleted'));
    }

    public function updateStatus(UpdatePostStatusRequest $request, Post $post): PostResource|JsonResponse
    {
        Gate::authorize('update', $post);

        // Don't allow changing status if the post is hidden (only admin can)
        if ($post->status === Post::STATUS_HIDDEN && ! auth()->user()->isAdmin()) {
            return response()->json([
                'message' => __('posts.cannot_change_hidden_status'),
            ], 403);
        }

        $post = $this->postService->updatePostStatus($post, $request->validated()['status']);

        return new PostResource($post);
    }

    public function vote(VotePostRequest $request, Post $post): JsonResponse
    {
        // Check if post is too old for voting
        $maxAgeDays = (int) config('posts.voting_max_age_days', 7);
        if ($post->created_at->diffInDays(now()) > $maxAgeDays) {
            return response()->json([
                'message' => __('messages.votes.too_old'),
            ], 403);
        }

        $validated = $request->validated();

        // If type is not provided, use a default value based on the value
        $value = (int) $validated['value'];
        $type = $validated['type'] ?? null;

        if (empty($type)) {
            $type = $value === 1 ? 'interesting' : 'irrelevant';
        }

        $result = $this->voteService->votePost($post, $value, $type);

        // Update last_visited_at when user votes on the post
        PostView::where('post_id', $post->id)
            ->where('user_id', auth()->id())
            ->update([
                'last_visited_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => $result['message'],
            'votes' => $result['votes'],
            'user_vote' => $value,
            'frontpage_reached' => $result['frontpage_reached'] ?? false,
        ]);
    }

    public function unvote(Post $post): JsonResponse
    {
        $result = $this->voteService->unvotePost($post);

        return response()->json([
            'message' => $result['message'],
            'votes' => $result['votes'],
            'user_vote' => null,
        ]);
    }

    public function voteStats(Post $post): JsonResponse
    {
        return response()->json($this->voteService->getVoteStats($post));
    }

    public function registerView(Post $post, Request $request): JsonResponse
    {
        Log::info('PostController: registerView endpoint called', [
            'post_id' => $post->id,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'skip_view_count' => $request->attributes->get('skip_view_count'),
        ]);

        // If marked to skip counting, return success without registering
        if ($request->attributes->get('skip_view_count')) {
            return response()->json([
                'success' => true,
                'message' => __('messages.posts.view_registered'),
                'views' => $post->views,
            ]);
        }

        $ip = $request->ip();
        $userId = auth()->id();
        $userAgent = $request->header('User-Agent');

        // Get tracking data from request
        $referer = $request->input('referer') ?: $request->header('Referer');
        $utmParams = [
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_term' => $request->input('utm_term'),
            'utm_content' => $request->input('utm_content'),
        ];
        $screenResolution = $request->input('screen_resolution');
        $sessionId = $request->input('session_id');
        $language = $request->query('locale');

        $viewService = app(ViewService::class);
        $registered = $viewService->registerView(
            $post,
            $ip,
            $userId,
            $userAgent,
            $referer,
            $utmParams,
            $screenResolution,
            $sessionId,
            $language,
        );

        if (! $registered) {
            return response()->json([
                'success' => false,
                'message' => __('messages.posts.view_already_registered'),
                'views' => $post->views,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.posts.view_registered'),
            'views' => $post->views,
        ]);
    }

    /**
     * Register impressions for multiple posts (batch endpoint).
     */
    public function registerImpressions(Request $request): JsonResponse
    {
        // Handle both JSON string (legacy) and array formats
        $postIds = $request->input('post_ids');

        if ($postIds === null) {
            return response()->json([
                'success' => false,
                'message' => __('messages.posts.no_post_ids'),
            ], 400);
        }

        if (is_string($postIds)) {
            $postIds = json_decode($postIds, true);
        }

        if (! is_array($postIds)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.posts.invalid_post_ids'),
            ], 400);
        }

        // Empty array is valid - just return 0 registered
        if (empty($postIds)) {
            return response()->json([
                'success' => true,
                'registered' => 0,
            ]);
        }

        // Limit to 100 posts per request and ensure all are integers
        $postIds = array_slice(array_filter($postIds, 'is_numeric'), 0, 100);
        $postIds = array_map('intval', $postIds);

        if (empty($postIds)) {
            return response()->json([
                'success' => true,
                'registered' => 0,
            ]);
        }

        $viewService = app(ViewService::class);
        $registered = $viewService->registerImpressions(
            $postIds,
            $request->ip(),
            auth()->id(),
        );

        return response()->json([
            'success' => true,
            'registered' => $registered,
        ]);
    }
}
