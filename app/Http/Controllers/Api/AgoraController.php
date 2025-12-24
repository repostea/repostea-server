<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\AgoraMessageCreated;
use App\Events\AgoraMessageDeleted;
use App\Events\AgoraMessageUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\AgoraMessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\AgoraMessage;
use App\Models\AgoraVote;
use App\Models\User;
use App\Notifications\AgoraMessageReplied;
use App\Notifications\AgoraUserMentioned;
use App\Services\KarmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

final class AgoraController extends Controller
{
    public function __construct(
        private KarmaService $karmaService,
    ) {}

    /**
     * Get all top-level messages with sorting options.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->input('per_page', 20), 100);
        $view = $request->input('view', 'threads'); // 'threads' or 'chronological'

        if ($view === 'chronological') {
            // Flat list of ALL messages (including replies) ordered by creation date
            $query = AgoraMessage::with(['user', 'parent.user'])
                ->selectRaw('agora_messages.*')
                ->orderBy('created_at', 'desc');
        } else {
            // Grouped by threads, ordered by last activity
            $query = AgoraMessage::whereNull('agora_messages.parent_id')
                ->with(['user'])
                ->selectRaw('agora_messages.*')
                ->selectRaw('(SELECT MAX(created_at) FROM agora_messages AS lm WHERE lm.root_id = agora_messages.id AND lm.id != agora_messages.id AND lm.deleted_at IS NULL) as last_reply_at')
                ->orderByRaw('COALESCE(last_reply_at, agora_messages.created_at) DESC');
        }

        // Add user vote if authenticated
        if (Auth::check()) {
            $query->leftJoin('agora_votes', function ($join): void {
                $join->on('agora_messages.id', '=', 'agora_votes.agora_message_id')
                    ->where('agora_votes.user_id', Auth::id());
            })
                ->addSelect('agora_votes.value as user_vote', 'agora_votes.vote_type as user_vote_type');
        }

        $messages = $query->paginate($perPage);

        // Load recent replies for each thread using root_id (much faster query)
        $rootIds = $messages->pluck('id')->toArray();
        if (! empty($rootIds)) {
            $recentReplies = $this->getRecentRepliesForThreads($rootIds, 10);

            foreach ($messages as $message) {
                $message->setRelation('replies', collect($recentReplies[$message->id] ?? []));
            }
        }

        return AgoraMessageResource::collection($messages);
    }

    /**
     * Get recent replies for multiple threads efficiently using root_id.
     *
     * @param  array<int>  $rootIds
     * @param  int  $limit  Per thread
     *
     * @return array<int, array>
     */
    private function getRecentRepliesForThreads(array $rootIds, int $limit = 10): array
    {
        // Get latest replies for each thread using a window function approach
        // This is much more efficient than N+1 queries
        $replies = AgoraMessage::whereIn('root_id', $rootIds)
            ->whereNotNull('parent_id') // Exclude root messages
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Add user votes if authenticated
        if (Auth::check()) {
            $replyIds = $replies->pluck('id')->toArray();
            $votes = AgoraVote::whereIn('agora_message_id', $replyIds)
                ->where('user_id', Auth::id())
                ->get()
                ->keyBy('agora_message_id');

            foreach ($replies as $reply) {
                $vote = $votes[$reply->id] ?? null;
                $reply->user_vote = $vote?->value;
                $reply->user_vote_type = $vote?->vote_type;
            }
        }

        // Group by root_id and limit per thread
        $grouped = [];
        foreach ($replies as $reply) {
            $rootId = $reply->root_id;
            if (! isset($grouped[$rootId])) {
                $grouped[$rootId] = [];
            }
            if (count($grouped[$rootId]) < $limit) {
                $grouped[$rootId][] = $reply;
            }
        }

        // Reverse order so oldest are first (for display)
        foreach ($grouped as $rootId => $threadReplies) {
            $grouped[$rootId] = array_reverse($threadReplies);
        }

        return $grouped;
    }

    /**
     * Get a single message with its full reply tree.
     */
    public function show(int $id): AgoraMessageResource|JsonResponse
    {
        $message = AgoraMessage::with(['user', 'replies' => function ($query): void {
            $this->loadRepliesRecursively($query);
        }])
            ->withCount('replies')
            ->find($id);

        if (! $message) {
            return ApiResponse::error(__('messages.agora.message_not_found'), null, 404);
        }

        // Add user vote if authenticated
        if (Auth::check()) {
            $vote = AgoraVote::where('agora_message_id', $id)
                ->where('user_id', Auth::id())
                ->first();
            $message->user_vote = $vote?->value;
            $message->user_vote_type = $vote?->vote_type;
        }

        return new AgoraMessageResource($message);
    }

    /**
     * Recursively load replies with user relation.
     */
    private function loadRepliesRecursively($query): void
    {
        $query->with(['user', 'replies' => function ($q): void {
            $this->loadRepliesRecursively($q);
        }])
            ->withCount('replies');

        if (Auth::check()) {
            $query->leftJoin('agora_votes', function ($join): void {
                $join->on('agora_messages.id', '=', 'agora_votes.agora_message_id')
                    ->where('agora_votes.user_id', Auth::id());
            })
                ->addSelect('agora_messages.*', 'agora_votes.value as user_vote', 'agora_votes.vote_type as user_vote_type');
        }
    }

    /**
     * Create a new message or reply.
     */
    public function store(Request $request): AgoraMessageResource|JsonResponse
    {
        $isReply = $request->has('parent_id') && $request->parent_id !== null;

        // Validation rules differ for top-level messages vs replies
        $rules = [
            'content' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:agora_messages,id',
            'is_anonymous' => 'nullable|boolean',
            'language_code' => 'nullable|string|size:2',
        ];

        // Top-level messages require expiry settings
        if (! $isReply) {
            $validExpiryHours = array_values(AgoraMessage::EXPIRY_OPTIONS);
            $rules['expires_in_hours'] = 'required|integer|in:' . implode(',', $validExpiryHours);
            $rules['expiry_mode'] = 'nullable|string|in:from_first,from_last';
        }

        $validated = $request->validate($rules);

        $messageData = [
            'user_id' => Auth::id(),
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $validated['content'],
            'is_anonymous' => $validated['is_anonymous'] ?? false,
            'language_code' => $validated['language_code'] ?? 'es',
        ];

        // Add expiry settings for top-level messages
        if (! $isReply) {
            $messageData['expires_in_hours'] = $validated['expires_in_hours'];
            $messageData['expiry_mode'] = $validated['expiry_mode'] ?? 'from_last';
        }

        $message = AgoraMessage::create($messageData);

        // Calculate expires_at for top-level messages
        if (! $isReply) {
            $message->calculateExpiresAt();
            $message->saveQuietly();
        }

        // The Observer handles: root_id, replies_count, total_replies_count

        // Get parent author for notifications and refresh expiry
        $parentAuthorId = null;
        if ($message->parent_id) {
            $parent = AgoraMessage::find($message->parent_id);
            if ($parent) {
                $parentAuthorId = $parent->user_id;
                // Refresh expiry if parent uses from_last mode
                $parent->refreshExpiryOnNewReply();

                // Send reply notification to parent author (if not replying to yourself)
                $parentUser = $parent->user;
                if ($parent->user_id !== Auth::id() && $parentUser instanceof User) {
                    $parentUser->notify(new AgoraMessageReplied($message, $parent, Auth::user()));
                }
            }
        }

        // Send mention notifications
        $this->notifyMentionedUsers($message, Auth::user());

        $message->load('user');

        broadcast(new AgoraMessageCreated($message, $parentAuthorId))->toOthers();

        return new AgoraMessageResource($message);
    }

    /**
     * Update a message.
     */
    public function update(Request $request, int $id): AgoraMessageResource|JsonResponse
    {
        $message = AgoraMessage::find($id);

        if (! $message) {
            return ApiResponse::error(__('messages.agora.message_not_found'), null, 404);
        }

        if ($message->user_id !== Auth::id()) {
            return ApiResponse::error(__('messages.agora.unauthorized'), null, 403);
        }

        // Build validation rules
        $rules = [
            'content' => 'required|string|max:5000',
        ];

        // Only top-level messages can have expiry settings and anonymous changed
        if (! $message->parent_id) {
            $validExpiryHours = array_values(AgoraMessage::EXPIRY_OPTIONS);
            $rules['expires_in_hours'] = 'nullable|integer|in:' . implode(',', $validExpiryHours);
            $rules['expiry_mode'] = 'nullable|string|in:from_first,from_last';
            $rules['is_anonymous'] = 'nullable|boolean';
        }

        $validated = $request->validate($rules);

        // Only set edited_at if content actually changed
        if ($message->content !== $validated['content']) {
            $validated['edited_at'] = now();
        }

        // Check if expiry settings changed (only for top-level messages)
        $expiryChanged = false;
        if (! $message->parent_id) {
            if (isset($validated['expires_in_hours']) && $validated['expires_in_hours'] !== $message->expires_in_hours) {
                $expiryChanged = true;
            }
            if (isset($validated['expiry_mode']) && $validated['expiry_mode'] !== $message->expiry_mode) {
                $expiryChanged = true;
            }

            // Validate that new expiry wouldn't cause immediate deletion
            if ($expiryChanged) {
                $newExpiresInHours = $validated['expires_in_hours'] ?? $message->expires_in_hours;
                $newExpiryMode = $validated['expiry_mode'] ?? $message->expiry_mode;

                // Calculate what the new expires_at would be
                $baseTime = $newExpiryMode === 'from_first'
                    ? $message->created_at
                    : ($message->getLatestActivityTime() ?? $message->created_at);
                $newExpiresAt = $baseTime->copy()->addHours($newExpiresInHours);

                // Ensure at least 5 minutes from now
                $minExpiresAt = now()->addMinutes(5);
                if ($newExpiresAt < $minExpiresAt) {
                    return ApiResponse::error(__('messages.agora.expiry_too_soon'), null, 422);
                }
            }
        }

        $message->update($validated);

        // Recalculate expires_at if expiry settings changed
        if ($expiryChanged) {
            $message->calculateExpiresAt();
            $message->saveQuietly();
        }

        // Broadcast update to all users viewing Agora
        broadcast(new AgoraMessageUpdated($message))->toOthers();

        return new AgoraMessageResource($message);
    }

    /**
     * Delete a message.
     */
    public function destroy(int $id): JsonResponse
    {
        $message = AgoraMessage::find($id);

        if (! $message) {
            return ApiResponse::error(__('messages.agora.message_not_found'), null, 404);
        }

        if ($message->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            return ApiResponse::error(__('messages.agora.unauthorized'), null, 403);
        }

        // Store IDs before deletion
        $messageId = $message->id;
        $parentId = $message->parent_id;

        // The Observer handles: replies_count, total_replies_count updates
        $message->delete();

        // Broadcast deletion to all users viewing Agora
        broadcast(new AgoraMessageDeleted($messageId, $parentId))->toOthers();

        return ApiResponse::success(null, __('messages.agora.deleted'));
    }

    /**
     * Vote on a message.
     */
    public function vote(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required|integer|in:-1,1',
            'vote_type' => 'nullable|string|in:didactic,interesting,elaborate,funny,incomplete,irrelevant,false,outofplace',
            'fingerprint' => 'nullable|string',
        ]);

        $message = AgoraMessage::find($id);

        if (! $message) {
            return ApiResponse::error(__('messages.agora.message_not_found'), null, 404);
        }

        // Get previous vote value to handle karma correctly
        $existingVote = AgoraVote::where('user_id', Auth::id())
            ->where('agora_message_id', $id)
            ->first();
        $previousValue = $existingVote?->value;

        $vote = AgoraVote::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'agora_message_id' => $id,
            ],
            [
                'value' => $validated['value'],
                'vote_type' => $validated['vote_type'] ?? null,
                'fingerprint' => $validated['fingerprint'] ?? null,
            ],
        );

        // Process karma for the vote
        $this->karmaService->processAgoraVoteKarma($vote, $previousValue);

        // Get fresh vote data
        $freshVote = AgoraVote::where('user_id', Auth::id())
            ->where('agora_message_id', $id)
            ->first();

        return ApiResponse::success([
            'votes_count' => $message->fresh()->votes_count,
            'user_vote' => $freshVote?->value,
            'user_vote_type' => $freshVote?->vote_type,
        ], __('messages.agora.vote_registered'));
    }

    /**
     * Remove vote from a message.
     */
    public function unvote(int $id): JsonResponse
    {
        $message = AgoraMessage::find($id);

        if (! $message) {
            return ApiResponse::error(__('messages.agora.message_not_found'), null, 404);
        }

        // Get vote before deleting to reverse karma
        $vote = AgoraVote::where('user_id', Auth::id())
            ->where('agora_message_id', $id)
            ->first();

        if ($vote) {
            $this->karmaService->reverseAgoraVoteKarma($vote);
            $vote->delete();
        }

        return ApiResponse::success([
            'votes_count' => $message->fresh()->votes_count,
        ], __('messages.agora.vote_removed'));
    }

    /**
     * Get recent messages from the Agora.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 5), 20);

        $messages = AgoraMessage::with(['user'])
            ->where('is_anonymous', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return ApiResponse::success([
            'data' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'content' => $m->content,
                'user' => [
                    'username' => $m->user->username,
                    'display_name' => $m->user->display_name,
                ],
                'created_at' => $m->created_at,
            ]),
        ]);
    }

    /**
     * Get top voted messages from the Agora.
     */
    public function tops(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 5), 20);
        $filter = $request->input('filter', 'top'); // top, funny, interesting, didactic, elaborate
        $days = min((int) $request->input('days', 3), 30);

        $query = AgoraMessage::with(['user'])
            ->where('is_anonymous', false)
            ->where('created_at', '>=', now()->subDays($days));

        if ($filter === 'top') {
            // Most voted (positive - negative)
            $query->withCount([
                'votes as positive_votes' => fn ($q) => $q->where('value', 1),
                'votes as negative_votes' => fn ($q) => $q->where('value', -1),
            ])
                ->orderByRaw('(positive_votes - negative_votes) DESC')
                ->orderBy('created_at', 'desc')
                ->having('positive_votes', '>=', 2);
        } else {
            // Filter by vote type
            $voteTypeMap = [
                'funny' => 'funny',
                'interesting' => 'interesting',
                'didactic' => 'didactic',
                'elaborate' => 'elaborate',
            ];

            if (isset($voteTypeMap[$filter])) {
                $query->withCount([
                    'votes as type_votes' => fn ($q) => $q->where('vote_type', $voteTypeMap[$filter])->where('value', 1),
                ])
                    ->orderBy('type_votes', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->having('type_votes', '>=', 2);
            }
        }

        $messages = $query->limit($limit)->get();

        return ApiResponse::success([
            'data' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'content' => $m->content,
                'user' => [
                    'username' => $m->user->username,
                    'display_name' => $m->user->display_name,
                ],
                'positive_votes' => $m->positive_votes ?? 0,
                'negative_votes' => $m->negative_votes ?? 0,
                'type_votes' => $m->type_votes ?? 0,
                'created_at' => $m->created_at,
            ]),
        ]);
    }

    /**
     * Extract and notify mentioned users.
     */
    private function notifyMentionedUsers(AgoraMessage $message, User $author): void
    {
        // Extract @mentions from content
        // Uses negative lookbehind to exclude @ preceded by / (inside URLs like mastodon.social/@user)
        preg_match_all('/(?<![\/\w])@([a-zA-Z0-9_-]+)/', $message->content, $matches);

        if (empty($matches[1])) {
            return;
        }

        $usernames = array_unique($matches[1]);

        // Find mentioned users and notify them
        User::whereIn('username', $usernames)
            ->where('id', '!=', $author->id) // Don't notify yourself
            ->get()
            ->each(function (User $user) use ($message, $author): void {
                $user->notify(new AgoraUserMentioned($message, $author));
            });
    }
}
