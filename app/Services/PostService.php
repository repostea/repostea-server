<?php

declare(strict_types=1);

namespace App\Services;

use const FILTER_VALIDATE_BOOLEAN;

use App\Models\ActivityPubPostSettings;
use App\Models\Post;
use App\Models\Sub;
use App\Models\Vote;
use Exception;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class PostService
{
    protected const CACHE_KEY_PATTERN = 'posts_%s';

    protected const CACHE_TTL = 300; // 5 minutes - with Redis this is fast and efficient

    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function getPosts(Request $request): Paginator|CursorPaginator
    {
        $cacheKey = $this->generateCacheKey($request);
        $useCursor = $request->has('cursor') || $request->input('pagination') === 'cursor';
        $cursorValue = $request->input('cursor');

        $postsData = Cache::tags(['posts'])->remember($cacheKey, now()->addSeconds(self::CACHE_TTL), function () use ($request, $useCursor, $cursorValue) {
            $query = Post::query();
            $this->applyFilters($query, $request->all());
            $this->applySorting($query, $request->all());

            $perPage = $request->input('per_page', 15);

            $query = $query->with([
                'user' => fn ($query) => $query->withTrashed(),
                'sub',
            ])->withCount([
                'reports' => function ($query): void {
                    $query->whereIn('status', ['pending', 'reviewing']);
                },
                'relationshipsAsSource',
                'relationshipsAsTarget',
            ]);

            // Use cursor-based pagination if requested
            if ($useCursor) {
                // Pass cursor explicitly since the Request object may not be the global request
                $cursor = $cursorValue ? \Illuminate\Pagination\Cursor::fromEncoded($cursorValue) : null;

                return $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
            }

            return $query->paginate($perPage);
        });

        $this->attachUserVotes($postsData);
        $this->attachUserVisitInfo($postsData);
        $this->attachUserSealMarks($postsData);
        $this->attachReportsForModerators($postsData);

        return $postsData;
    }

    public function getFrontpage(Request $request): Paginator|CursorPaginator
    {
        $request->merge(['section' => 'frontpage']);

        return $this->getPosts($request);
    }

    public function getPending(Request $request): Paginator|CursorPaginator
    {
        $request->merge(['section' => 'pending']);

        return $this->getPosts($request);
    }

    public function getPendingCount(int $hours = 24, ?int $userId = null): int
    {
        // For authenticated users, show personalized count (excluding voted/visited posts)
        if ($userId !== null) {
            $cacheKey = "posts_pending_count_{$hours}h_user_{$userId}";
            $cacheTtl = 300; // 5 minutes for personalized counts

            $count = Cache::tags(['posts', "user_{$userId}_pending"])->remember($cacheKey, $cacheTtl, function () use ($hours, $userId) {
                return Post::where('created_at', '>=', now()->subHours($hours))
                    ->whereNull('frontpage_at')
                    ->where('status', 'published')
                    ->whereHas('user', function ($query): void {
                        $query->where('is_deleted', '!=', true)
                            ->orWhereNull('is_deleted');
                    })
                    // Exclude posts the user has voted on
                    ->whereDoesntHave('votes', function ($query) use ($userId): void {
                        $query->where('user_id', $userId);
                    })
                    // Exclude posts the user has visited
                    ->whereDoesntHave('views', function ($query) use ($userId): void {
                        $query->where('user_id', $userId);
                    })
                    ->count();
            });

            return (int) $count;
        }

        // For anonymous users, show global count (cached for 1 hour)
        $count = Cache::tags(['posts'])->remember("posts_pending_count_{$hours}h", 3600, function () use ($hours) {
            return Post::where('created_at', '>=', now()->subHours($hours))
                ->whereNull('frontpage_at')
                ->where('status', 'published')
                ->whereHas('user', function ($query): void {
                    $query->where('is_deleted', '!=', true)
                        ->orWhereNull('is_deleted');
                })
                ->count();
        });

        // Ensure we return an integer (cache might return string)
        return (int) $count;
    }

    public function getBySlug(string $slug): ?Post
    {
        $post = Post::where('slug', $slug)
            ->with([
                'user' => fn ($query) => $query->withTrashed(),
                'sub',
            ])
            ->first();

        if ($post !== null) {
            // Check if post is hidden or draft and user is not the author
            if (($post->status === 'hidden' || $post->status === 'draft') && (! Auth::check() || Auth::id() !== $post->user_id)) {
                return null; // Return null to trigger 404 or "content removed" message
            }
            $this->preparePostForDisplay($post);
        }

        return $post;
    }

    public function getByUuid(string $uuid): ?Post
    {
        $post = Post::where('uuid', $uuid)->with(['user' => fn ($query) => $query->withTrashed()])->first();

        if ($post !== null) {
            // Check if post is hidden or draft and user is not the author
            if (($post->status === 'hidden' || $post->status === 'draft') && (! Auth::check() || Auth::id() !== $post->user_id)) {
                return null; // Return null to trigger 404 or "content removed" message
            }
            $this->preparePostForDisplay($post);
        }

        return $post;
    }

    public function getById(int $id): ?Post
    {
        $post = Post::find($id);

        if ($post !== null) {
            // Check if post is hidden or draft and user is not the author
            if (($post->status === 'hidden' || $post->status === 'draft') && (! Auth::check() || Auth::id() !== $post->user_id)) {
                return null; // Return null to trigger 404 or "content removed" message
            }
            $this->preparePostForDisplay($post);
        }

        return $post;
    }

    public function createPost(array $data): Post
    {
        $data['user_id'] = Auth::id();

        // Validate content type is allowed in the sub and check require_approval
        $sub = null;
        if (! empty($data['sub_id'])) {
            $sub = Sub::find($data['sub_id']);
            if ($sub && ! empty($sub->allowed_content_types)) {
                $contentType = $data['content_type'] ?? 'text';
                if (! in_array($contentType, $sub->allowed_content_types, true)) {
                    throw new Exception(__('posts.content_type_not_allowed', [
                        'type' => $contentType,
                        'sub' => $sub->name,
                    ]));
                }
            }
            // Force pending status if sub requires approval
            if ($sub && $sub->require_approval) {
                $data['status'] = 'pending';
            }
        }

        // Store poll options in media_metadata if content_type is poll
        if (($data['content_type'] ?? null) === 'poll' && ! empty($data['poll_options']) && is_array($data['poll_options'])) {
            $data['media_metadata'] = json_encode([
                'poll_options' => $data['poll_options'],
                'expires_at' => $data['expires_at'] ?? null,
                'allow_multiple_options' => $data['allow_multiple_options'] ?? false,
            ]);
        }

        // Auto-detect media provider and content_type from URL
        if (! empty($data['url'])) {
            $detectedProvider = $this->mediaService->detectMediaProvider($data['url']);
            if ($detectedProvider) {
                $data['media_provider'] = $detectedProvider;
                $mediaType = $this->mediaService->getMediaType($detectedProvider);
                if ($mediaType && in_array($mediaType, ['video', 'audio'], true)) {
                    // Override content_type if it's 'link' or not set
                    if (empty($data['content_type']) || $data['content_type'] === 'link') {
                        $data['content_type'] = $mediaType;
                    }
                }
            }
        }

        $post = Post::create($data);

        // Auto-vote: author automatically upvotes their own post
        $post->votes()->create([
            'user_id' => $data['user_id'],
            'value' => Vote::VALUE_POSITIVE,
            'type' => Vote::TYPE_INTERESTING,
        ]);
        $post->updateVotesCount();

        // Auto-subscribe user to sub if posting to a sub they're not subscribed to
        if ($sub !== null) {
            $userId = Auth::id();
            $isSubscribed = $sub->subscribers()->where('user_id', $userId)->exists();

            if (! $isSubscribed) {
                $sub->subscribers()->attach($userId, ['status' => 'active']);
                // Update members count to match active subscribers
                $count = $sub->subscribers()->wherePivot('status', 'active')->count();
                $sub->update(['members_count' => $count]);
            }
        }

        if (! empty($data['tag_ids']) && is_array($data['tag_ids'])) {
            $post->syncTags($data['tag_ids']);
        }

        $post->load(['user', 'tags', 'sub']);

        // Note: Cache invalidation happens automatically via PostObserver

        // Dispatch async duplicate content check
        \App\Jobs\CheckContentDuplicate::dispatch('post', $post->id, $post->user_id);

        // Create ActivityPub post settings if should_federate was explicitly set
        if (isset($data['should_federate'])) {
            ActivityPubPostSettings::create([
                'post_id' => $post->id,
                'should_federate' => (bool) $data['should_federate'],
                'is_federated' => false,
            ]);
        }

        // Dispatch federation job if enabled and post is published
        $this->dispatchFederationIfEnabled($post);

        return $post;
    }

    public function importPost(array $data): Post
    {
        $data['user_id'] = Auth::id();
        $data['is_original'] = false;
        $data['content_type'] = $data['content_type'] ?? (empty($data['url']) ? 'text' : 'link');

        // Auto-detect media provider and content_type from URL
        if (! empty($data['url'])) {
            $detectedProvider = $this->mediaService->detectMediaProvider($data['url']);
            if ($detectedProvider) {
                $data['media_provider'] = $detectedProvider;
                $mediaType = $this->mediaService->getMediaType($detectedProvider);
                if ($mediaType && in_array($mediaType, ['video', 'audio'], true)) {
                    if (empty($data['content_type']) || $data['content_type'] === 'link') {
                        $data['content_type'] = $mediaType;
                    }
                }
            }
        }

        try {
            $post = Post::create($data);

            // Auto-vote: author automatically upvotes their own post
            $post->votes()->create([
                'user_id' => $data['user_id'],
                'value' => Vote::VALUE_POSITIVE,
                'type' => Vote::TYPE_INTERESTING,
            ]);
            $post->updateVotesCount();

            Log::info('Content imported successfully', [
                'user_id' => Auth::id(),
                'post_id' => $post->id,
                'source' => $post->getSourceName(),
                'content_type' => $post->content_type,
                'media_provider' => $post->media_provider,
            ]);

            if (! empty($data['tag_ids']) && is_array($data['tag_ids'])) {
                $post->syncTags($data['tag_ids']);
            }

            return $post;
        } catch (Exception $e) {
            Log::error('Error importing content', [
                'user_id' => Auth::id(),
                'url' => $data['url'] ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function updatePost(Post $post, array $data): Post
    {
        // Check if trying to edit poll options when poll has votes
        if ($post->content_type === 'poll' &&
            isset($data['poll_options']) &&
            $post->pollVotes()->exists()) {
            throw new Exception(__('polls.cannot_edit_with_votes'));
        }

        // Check if language is locked by admin
        if ($post->language_locked_by_admin &&
            isset($data['language_code']) &&
            $data['language_code'] !== $post->language_code) {
            throw new Exception(__('posts.language_locked_by_moderator'));
        }

        // Check if NSFW status is locked by admin
        if ($post->nsfw_locked_by_admin &&
            isset($data['is_nsfw']) &&
            $data['is_nsfw'] !== $post->is_nsfw) {
            throw new Exception(__('posts.nsfw_locked_by_moderator'));
        }

        // Store poll options in media_metadata if content_type is poll
        if (isset($data['content_type']) && $data['content_type'] === 'poll' &&
            ! empty($data['poll_options']) && is_array($data['poll_options'])) {
            $data['media_metadata'] = json_encode([
                'poll_options' => $data['poll_options'],
                'expires_at' => $data['expires_at'] ?? null,
                'allow_multiple_options' => $data['allow_multiple_options'] ?? false,
            ]);
        }

        // Auto-detect media provider and content_type from URL
        if (! empty($data['url'])) {
            $detectedProvider = $this->mediaService->detectMediaProvider($data['url']);
            if ($detectedProvider) {
                $data['media_provider'] = $detectedProvider;
                $mediaType = $this->mediaService->getMediaType($detectedProvider);
                if ($mediaType && in_array($mediaType, ['video', 'audio'], true)) {
                    $currentContentType = $data['content_type'] ?? $post->content_type;
                    if (empty($currentContentType) || $currentContentType === 'link') {
                        $data['content_type'] = $mediaType;
                    }
                }
            }
        }

        // Capture old status before update for federation logic
        $oldStatus = $post->status;

        $post->update($data);

        // Handle status change for federation (if status was changed via $data)
        if (isset($data['status']) && $data['status'] !== $oldStatus && config('activitypub.enabled', false)) {
            $postSettings = ActivityPubPostSettings::where('post_id', $post->id)->first();
            $wasFederated = $postSettings?->is_federated ?? false;
            $shouldFederate = $postSettings?->should_federate ?? false;

            // If changing from published to draft/hidden and was federated, send Delete
            if ($oldStatus === 'published' && in_array($data['status'], ['draft', 'hidden'], true) && $wasFederated) {
                \App\Jobs\DeliverPostDelete::dispatch(
                    $post->id,
                    $post->user_id,
                    $post->sub_id,
                );
            }

            // If changing to published and should_federate is enabled, dispatch federation
            if ($data['status'] === 'published' && $oldStatus !== 'published' && $shouldFederate) {
                $this->dispatchFederationIfEnabled($post);
            }
        }

        // Auto-subscribe user to sub if changing to a sub they're not subscribed to
        if (! empty($data['sub_id'])) {
            $sub = Sub::find($data['sub_id']);
            if ($sub !== null) {
                $userId = Auth::id();
                $isSubscribed = $sub->subscribers()->where('user_id', $userId)->exists();

                if (! $isSubscribed) {
                    $sub->subscribers()->attach($userId, ['status' => 'active']);
                    // Update members count to match active subscribers
                    $count = $sub->subscribers()->wherePivot('status', 'active')->count();
                    $sub->update(['members_count' => $count]);
                }
            }
        }

        if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
            $post->syncTags($data['tag_ids']);
        }

        $post->load(['user', 'tags', 'sub']);

        // Note: Cache invalidation happens automatically via PostObserver

        // Handle ActivityPub federation toggle
        if (isset($data['should_federate'])) {
            $shouldFederate = (bool) $data['should_federate'];
            $postSettings = ActivityPubPostSettings::where('post_id', $post->id)->first();
            $wasFederated = $postSettings?->is_federated ?? false;

            if ($postSettings !== null) {
                $postSettings->update(['should_federate' => $shouldFederate]);
            } else {
                ActivityPubPostSettings::create([
                    'post_id' => $post->id,
                    'should_federate' => $shouldFederate,
                    'is_federated' => false,
                ]);
            }

            // If user is removing from federation and post was federated, send Delete
            if (! $shouldFederate && $wasFederated) {
                \App\Jobs\DeliverPostDelete::dispatch(
                    $post->id,
                    $post->user_id,
                    $post->sub_id,
                );

                return $post;
            }
        }

        // Dispatch Update activity to federated instances if post was federated
        $this->dispatchUpdateIfFederated($post);

        // Dispatch federation job if enabled, post is published, and should_federate is true
        if ($post->status === 'published') {
            $this->dispatchFederationIfEnabled($post);
        }

        return $post;
    }

    public function updatePostStatus(Post $post, string $status): Post
    {
        $oldStatus = $post->status;
        $post->status = $status;
        $post->save();

        // Note: Cache invalidation happens automatically via PostObserver

        // Handle ActivityPub federation based on status change
        if (config('activitypub.enabled', false)) {
            $postSettings = ActivityPubPostSettings::where('post_id', $post->id)->first();
            $wasFederated = $postSettings?->is_federated ?? false;
            $shouldFederate = $postSettings?->should_federate ?? false;

            // If changing from published to draft/hidden and was federated, send Delete
            if ($oldStatus === 'published' && in_array($status, ['draft', 'hidden'], true) && $wasFederated) {
                \App\Jobs\DeliverPostDelete::dispatch(
                    $post->id,
                    $post->user_id,
                    $post->sub_id,
                );
            }

            // If changing to published and should_federate is enabled, dispatch federation
            if ($status === 'published' && $oldStatus !== 'published' && $shouldFederate) {
                $this->dispatchFederationIfEnabled($post);
            }
        }

        return $post;
    }

    public function deletePost(Post $post): bool
    {
        $result = $post->delete();

        // Note: Cache invalidation happens automatically via PostObserver

        return $result;
    }

    protected function preparePostForDisplay(Post $post): void
    {
        $post->load(['user', 'tags', 'sub']);
        $post->loadCount([
            'reports' => function ($query): void {
                $query->whereIn('status', ['pending', 'reviewing']);
            },
            'relationshipsAsSource',
            'relationshipsAsTarget',
        ]);

        if (Auth::check()) {
            $post->load('reports');

            $userVote = $post->votes()
                ->where('user_id', Auth::id())
                ->first();

            $post->user_vote = $userVote?->value;
            $post->user_vote_type = $userVote?->type;

            // Add visit info for single post view
            $visitRecord = \Illuminate\Support\Facades\DB::table('post_views')
                ->where('post_id', $post->id)
                ->where('user_id', Auth::id())
                ->first();

            if ($visitRecord !== null) {
                $post->is_visited = true;
                $post->last_visited_at = $visitRecord->last_visited_at ?? $visitRecord->updated_at;

                // Count comments created after last visit, excluding user's own comments
                // Only count if we have a valid last_visited_at timestamp
                if ($post->last_visited_at !== null) {
                    $post->new_comments_count = \App\Models\Comment::where('post_id', $post->id)
                        ->where('created_at', '>', $post->last_visited_at)
                        ->where('user_id', '!=', Auth::id())
                        ->count();
                } else {
                    $post->new_comments_count = 0;
                }
            } else {
                $post->is_visited = false;
                $post->last_visited_at = null;
                $post->new_comments_count = 0;
            }

            // Attach user seal marks
            $userSealMarks = \App\Models\SealMark::where('user_id', Auth::id())
                ->where('markable_type', Post::class)
                ->where('markable_id', $post->id)
                ->where('expires_at', '>', now())
                ->get();

            $post->user_has_recommended = $userSealMarks->contains('type', 'recommended');
            $post->user_has_advise_against = $userSealMarks->contains('type', 'advise_against');
        } else {
            $post->is_visited = false;
            $post->last_visited_at = null;
            $post->new_comments_count = 0;
            $post->user_has_recommended = false;
            $post->user_has_advise_against = false;
        }

        $post->vote_details = $post->votes()->get()->toArray();
        // Views are now tracked via ViewService with cooldown - removed automatic increment
    }

    protected function attachUserVotes($posts): void
    {
        if (Auth::check()) {
            $postIds = $posts->pluck('id')->toArray();

            $userVotes = Vote::where('user_id', Auth::id())
                ->where('votable_type', Post::class)
                ->whereIn('votable_id', $postIds)
                ->get()
                ->keyBy('votable_id');

            $posts->each(static function ($post) use ($userVotes): void {
                $userVote = $userVotes->get($post->id);
                $post->user_vote = $userVote !== null ? $userVote->value : null;
                $post->user_vote_type = $userVote !== null ? $userVote->type : null;
            });
        }
    }

    protected function attachUserVisitInfo($posts): void
    {
        if (Auth::check()) {
            $postIds = $posts->pluck('id')->toArray();
            $userId = Auth::id();

            // Get all user visits for these posts
            $userVisits = \Illuminate\Support\Facades\DB::table('post_views')
                ->where('user_id', $userId)
                ->whereIn('post_id', $postIds)
                ->get()
                ->keyBy('post_id');

            // Optimized: Get new comments count for all posts in a single query
            $newCommentsCounts = $this->getNewCommentsCount($posts, $userVisits, $userId);

            $posts->each(function ($post) use ($userVisits, $newCommentsCounts): void {
                $visitRecord = $userVisits->get($post->id);

                if ($visitRecord !== null) {
                    $post->is_visited = true;
                    $post->last_visited_at = $visitRecord->last_visited_at ?? $visitRecord->updated_at;
                    $post->new_comments_count = $newCommentsCounts[$post->id] ?? 0;
                } else {
                    $post->is_visited = false;
                    $post->last_visited_at = null;
                    $post->new_comments_count = 0;
                }
            });
        } else {
            // For anonymous users, mark all as not visited
            $posts->each(static function ($post): void {
                $post->is_visited = false;
                $post->last_visited_at = null;
                $post->new_comments_count = 0;
            });
        }
    }

    /**
     * Get new comments count for multiple posts in a single query.
     */
    protected function getNewCommentsCount($posts, $userVisits, int $userId): array
    {
        $counts = [];

        // Build conditions for each post with visit info
        $conditions = [];
        foreach ($posts as $post) {
            $visitRecord = $userVisits->get($post->id);
            if ($visitRecord !== null && $visitRecord->last_visited_at !== null) {
                $conditions[] = [
                    'post_id' => $post->id,
                    'last_visited_at' => $visitRecord->last_visited_at,
                ];
            }
        }

        // If no posts with visits, return empty array
        if (empty($conditions)) {
            return $counts;
        }

        // Single query with GROUP BY to get counts for all posts
        $results = \App\Models\Comment::select('post_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->whereIn('post_id', array_column($conditions, 'post_id'))
            ->where('user_id', '!=', $userId)
            ->where(function ($query) use ($conditions): void {
                foreach ($conditions as $condition) {
                    $query->orWhere(function ($q) use ($condition): void {
                        $q->where('post_id', $condition['post_id'])
                            ->where('created_at', '>', $condition['last_visited_at']);
                    });
                }
            })
            ->groupBy('post_id')
            ->pluck('count', 'post_id')
            ->toArray();

        return $results;
    }

    protected function attachUserSealMarks($posts): void
    {
        if (Auth::check()) {
            $postIds = $posts->pluck('id')->toArray();

            // Get all active seal marks for these posts from current user
            $userSealMarks = \App\Models\SealMark::where('user_id', Auth::id())
                ->where('markable_type', Post::class)
                ->whereIn('markable_id', $postIds)
                ->where('expires_at', '>', now())
                ->get();

            // Group by post ID and type
            $sealMarksByPost = $userSealMarks->groupBy('markable_id');

            $posts->each(function ($post) use ($sealMarksByPost): void {
                $postMarks = $sealMarksByPost->get($post->id);

                $post->user_has_recommended = $postMarks !== null && $postMarks->contains('type', 'recommended');
                $post->user_has_advise_against = $postMarks !== null && $postMarks->contains('type', 'advise_against');
            });
        } else {
            // For anonymous users, mark all as false
            $posts->each(static function ($post): void {
                $post->user_has_recommended = false;
                $post->user_has_advise_against = false;
            });
        }
    }

    protected function attachReportsForModerators($posts): void
    {
        if (Auth::check()) {
            $posts->load('reports');
        }
    }

    protected function applyFilters($query, array $filters = []): void
    {
        // Exclude posts from deleted users
        $query->whereHas('user', function ($q): void {
            $q->whereNull('deleted_at');
        });

        // Filter NSFW content based on user preferences
        if (Auth::check()) {
            $userPreference = Auth::user()->preferences ?? null;
            if ($userPreference && $userPreference->hide_nsfw) {
                $query->where('is_nsfw', false);
            }
        }

        // Only show published posts in public listings by default
        // Show all statuses only when viewing own profile
        $showHidden = isset($filters['show_hidden']) && $filters['show_hidden'];
        $isOwnProfile = isset($filters['user_id']) && Auth::check() && (int) $filters['user_id'] === Auth::id();

        if (! $showHidden) {
            if ($isOwnProfile) {
                // On own profile, show all posts (published, draft, hidden) - they will be marked in the frontend
                // No filter needed
            } else {
                // On all other listings, only show published posts
                $query->where('status', 'published');
            }
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['search'])) {
            $searchTerm = $filters['search'];
            // Convert string "true"/"false" to boolean
            $searchInComments = filter_var($filters['search_in_comments'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // Split search into words for flexible matching (like Google)
            $searchWords = array_filter(explode(' ', $searchTerm), fn ($word) => strlen(trim($word)) > 0);

            $query->where(static function ($q) use ($searchWords, $searchInComments): void {
                foreach ($searchWords as $word) {
                    $word = trim($word);
                    if (strlen($word) === 0) {
                        continue;
                    }

                    $q->where(function ($subQuery) use ($word, $searchInComments): void {
                        // Search in title and content
                        $subQuery->where('title', 'like', '%' . $word . '%')
                            ->orWhere('content', 'like', '%' . $word . '%');

                        // Search in tags
                        $subQuery->orWhereHas('tags', function ($tagQuery) use ($word): void {
                            $tagQuery->where('name_key', 'like', '%' . $word . '%')
                                ->orWhere('slug', 'like', '%' . $word . '%');
                        });

                        // Search in comments if enabled
                        if ($searchInComments) {
                            $subQuery->orWhereHas('comments', function ($commentQuery) use ($word): void {
                                $commentQuery->where('content', 'like', '%' . $word . '%');
                            });
                        }
                    });
                }
            });

            // Load matching comment for search results
            if ($searchInComments) {
                $query->with(['comments' => function ($commentQuery) use ($searchWords): void {
                    foreach ($searchWords as $word) {
                        $word = trim($word);
                        if (strlen($word) > 0) {
                            $commentQuery->where('content', 'like', '%' . $word . '%');
                        }
                    }
                    $commentQuery->orderBy('created_at', 'asc')->limit(1);
                }]);
            }
        }

        // Filter by featured/frontpage status
        if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
            // Convert string "true"/"false" to boolean
            $isFeatured = filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN);

            if ($isFeatured) {
                $query->whereNotNull('frontpage_at');
            } else {
                $query->whereNull('frontpage_at');
            }
        }

        if (isset($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }

        if (isset($filters['media_provider'])) {
            $query->where('media_provider', $filters['media_provider']);
        }

        // Filter by languages
        if (isset($filters['languages']) && ! empty($filters['languages'])) {
            $languages = is_array($filters['languages']) ? $filters['languages'] : explode(',', $filters['languages']);
            $languages = array_map('trim', $languages);
            $query->whereIn('language_code', $languages);
        }

        // Filter by source (my-subs)
        if (isset($filters['source']) && $filters['source'] === 'my-subs') {
            if (Auth::check()) {
                // Get user's subscribed sub IDs
                $subscribedSubIds = Auth::user()->subscribedSubs()->pluck('subs.id');

                // Only show posts from subscribed subs
                $query->whereIn('sub_id', $subscribedSubIds);
            } else {
                // If not authenticated, return no posts
                $query->whereRaw('1 = 0');
            }
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        if (isset($filters['time_interval']) && $filters['time_interval'] > 0 && $sortBy !== 'lastActive') {
            $interval = now()->subMinutes($filters['time_interval']);
            $query->where('created_at', '>=', $interval);
        }

        if (isset($filters['section'])) {
            // Don't apply section filters when using source filters (like my-subs)
            $hasSourceFilter = isset($filters['source']) && ! empty($filters['source']);

            if (! $hasSourceFilter) {
                if ($filters['section'] === 'frontpage') {
                    $this->applyFrontpageFilter($query);
                } elseif ($filters['section'] === 'pending') {
                    $this->applyPendingFilter($query);
                }
            }
        }
    }

    protected function applyFrontpageFilter($query): void
    {
        // Frontpage shows ONLY posts that have reached frontpage (frontpage_at is set)
        $query->whereNotNull('frontpage_at');
    }

    protected function applyPendingFilter($query): void
    {
        // Posts in pending: all published posts that have not reached frontpage yet
        // Votes don't matter - they only determine WHEN posts auto-promote to frontpage
        $query->whereNull('frontpage_at');
    }

    protected function applySorting($query, array $filters = []): void
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        // Validate sortDir to prevent SQL injection
        $sortDir = in_array(strtolower($sortDir), ['asc', 'desc'], true) ? strtolower($sortDir) : 'desc';

        $isFrontpage = isset($filters['section']) && $filters['section'] === 'frontpage';
        $isPending = isset($filters['section']) && $filters['section'] === 'pending';
        $useCursor = isset($filters['pagination']) && $filters['pagination'] === 'cursor';

        if ($sortBy === 'lastActive') {
            $sortBy = 'created_at';
        } elseif ($sortBy === 'uv' || $sortBy === 'favourites') {
            $sortBy = 'votes_count';
        } elseif ($sortBy === 'comments') {
            $sortBy = 'comment_count';
        } elseif ($sortBy === 'views') {
            $sortBy = 'views';
        }

        // Frontpage: sort by frontpage_at (when post reached frontpage)
        if ($isFrontpage && $sortBy === 'created_at') {
            if ($useCursor) {
                $query->orderBy('frontpage_at', $sortDir)->orderBy('id', $sortDir);
            } else {
                $query->orderByRaw('COALESCE(frontpage_at, created_at) ' . $sortDir);
            }
        }
        // Pending: sort by published_at (when post was first published)
        elseif ($isPending && $sortBy === 'created_at') {
            if ($useCursor) {
                $query->orderBy('published_at', $sortDir)->orderBy('id', $sortDir);
            } else {
                $query->orderByRaw('COALESCE(published_at, created_at) ' . $sortDir);
            }
        } else {
            $query->orderBy($sortBy, $sortDir);
            if ($useCursor) {
                $query->orderBy('id', $sortDir);
            }
        }
    }

    protected function generateCacheKey(Request $request, ?string $section = null): string
    {
        $params = $request->except(['auth_token', 'api_token', 'token']);

        if ($section !== null) {
            $params['section'] = $section;
        }

        return sprintf(self::CACHE_KEY_PATTERN, md5(json_encode($params)));
    }

    // Note: Cache invalidation now happens automatically via PostObserver
    // The old clearPostsCache() method was doing Cache::flush() which cleared
    // ALL cache (rankings, users, sessions, etc.) - very inefficient!
    // PostObserver now clears only relevant post listing caches.

    /**
     * Dispatch federation job if automatic federation is enabled.
     */
    protected function dispatchFederationIfEnabled(Post $post): void
    {
        // Only federate published posts
        if ($post->status !== 'published') {
            return;
        }

        // Check if ActivityPub is enabled globally
        if (! config('activitypub.enabled', false)) {
            return;
        }

        // Check if automatic federation is enabled in system settings
        if (! \App\Models\SystemSetting::get('federation_auto_publish', true)) {
            return;
        }

        // Dispatch the federation job (it will check user/post/sub settings internally)
        \App\Jobs\DeliverMultiActorPost::dispatch($post);
    }

    /**
     * Dispatch Update federation job if post has been federated.
     */
    protected function dispatchUpdateIfFederated(Post $post): void
    {
        // Only send updates for published posts
        if ($post->status !== 'published') {
            return;
        }

        // Check if ActivityPub is enabled globally
        if (! config('activitypub.enabled', false)) {
            return;
        }

        // Dispatch the update job (it will check if post was federated internally)
        \App\Jobs\DeliverPostUpdate::dispatch($post);
    }
}
