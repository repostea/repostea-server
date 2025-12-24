<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\CommentWithPostResource;
use App\Http\Resources\PostCollection;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Achievement;
use App\Models\KarmaLevel;
use App\Models\User;
use App\Services\KarmaService;
use Exception;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Str;

final class UserController extends Controller
{
    protected $karmaService;

    public function __construct(KarmaService $karmaService)
    {
        $this->karmaService = $karmaService;
    }

    public function current(Request $request)
    {
        $user = $request->user();
        // Assuming these relationships exist in the User model
        $user->load(['karmaLevel', 'streak']);

        return response()->json([
            'user' => new UserResource($user),
            'karma_multiplier' => $user->karma_multiplier,
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        $user->load(['karmaLevel', 'streak']);

        $achievements = $this->getUserAchievements($user);

        return response()->json([
            'user' => new UserResource($user),
            'streak' => $user->streak,
            'karma_multiplier' => $user->karma_multiplier,
            'achievements' => [
                'items' => $achievements->groupBy('type'),
                'unlocked_count' => $achievements->where('unlocked', true)->count(),
                'total_count' => $achievements->count(),
            ],
        ]);
    }

    public function getByUsername($username)
    {
        $user = User::where('username', $username)
            ->withTrashed()
            ->withCount([
                'posts' => function ($query): void {
                    $query->where('is_anonymous', false);
                },
                'comments' => function ($query): void {
                    $query->where('is_anonymous', false);
                },
                'votes',
            ])
            ->first();

        if (! $user) {
            return response()->json([
                'message' => __('messages.users.not_found'),
            ], 404);
        }

        if ($user->deleted_at) {
            return response()->json([
                'message' => __('messages.users.account_deleted'),
            ], 404);
        }
        $user->load(['currentLevel', 'streak', 'preferences']);

        // Always respect privacy settings in public profile view (even for own profile)
        // Users should see their public profile the same way others see it
        $hideAchievements = $user->preferences && $user->preferences->hide_achievements;

        $achievements = null;
        if (! $hideAchievements) {
            $achievementsList = $this->getUserAchievements($user);

            $achievements = [
                'items' => $achievementsList->groupBy('type'),
                'unlocked_count' => $achievementsList->where('unlocked', true)->count(),
                'total_count' => $achievementsList->count(),
            ];
        }

        return response()->json([
            'data' => new UserResource($user),
            'achievements' => $achievements,
        ]);
    }

    public function getUserPosts($username)
    {
        $user = User::where('username', $username)->withTrashed()->first();

        if (! $user || $user->deleted_at) {
            return response()->json([
                'message' => __('messages.users.not_found_or_deleted'),
            ], 404);
        }
        $posts = $user->posts()
            ->where('is_anonymous', false)
            ->where('status', 'published') // Only show published posts in public profile
            ->with(['tags', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 15));

        return new PostCollection($posts);
    }

    public function getUserComments($username)
    {
        $user = User::where('username', $username)->withTrashed()->with('preferences')->first();

        if (! $user || $user->deleted_at) {
            return response()->json([
                'message' => __('messages.users.not_found_or_deleted'),
            ], 404);
        }

        // Always respect privacy settings in public profile view (even for own profile)
        // Users should see their public profile the same way others see it
        $hideComments = $user->preferences && $user->preferences->hide_comments;

        if ($hideComments) {
            return response()->json([
                'message' => __('messages.comments.hidden_by_user'),
            ], 403);
        }

        $comments = $user->comments()
            ->where('is_anonymous', false)
            ->with(['post'])
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 15));

        return [
            'data' => CommentWithPostResource::collection($comments),
            'meta' => [
                'total' => $comments->total(),
                'per_page' => $comments->perPage(),
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
            ],
        ];
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        if (isset($data['email']) && $data['email'] !== $user->email) {
            return response()->json([
                'message' => __('messages.profile.email_change_disabled'),
                'errors' => ['email' => [__('messages.profile.email_not_allowed')]],
            ], 422);
        }

        if (isset($data['username']) && $data['username'] !== $user->username) {
            $user->username = $data['username'];
        }

        if (isset($data['avatar_url']) && ! empty($data['avatar_url'])) {
            $user->avatarUrl = $data['avatar_url']; // Assuming 'avatarUrl' is the proper property name
        }

        $fieldsToUpdate = [
            'bio',
            'professional_title',
            'institution',
            'academic_degree',
            'expertise_areas',
        ];

        foreach ($fieldsToUpdate as $field) {
            if (isset($data[$field])) {
                $user->{$field} = $data[$field];
            }
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $user->tags()->sync($data['tags']);
        }

        $user->save();

        return response()->json([
            'message' => __('messages.profile.updated'),
            'user' => new UserResource($user),
        ]);
    }

    public function show($id)
    {
        $user = User::withCount(['posts', 'comments'])->findOrFail($id);
        $user->load(['karmaLevel']);

        return response()->json([
            'user' => new UserResource($user),
            'posts_count' => $user->posts_count,
            'comments_count' => $user->comments_count,
            'member_since' => $user->created_at->format('M Y'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'locale' => ['sometimes', 'string', Rule::in(['en', 'es', 'fr', 'de', 'pt'])],
            'theme' => ['sometimes', 'string', Rule::in(['light', 'dark', 'system'])],
            'email_notifications' => 'sometimes|boolean',
            'content_preferences' => 'sometimes|array',
        ]);

        $user->userSettings = array_merge($user->userSettings ?? [], $validated); // Assuming the property is called userSettings
        $user->save();

        return response()->json([
            'message' => __('messages.settings.updated'),
            'settings' => $user->userSettings,
        ]);
    }

    public function karma(Request $request)
    {
        $user = $request->user();
        $user->load(['karmaLevel', 'streak']);

        $userLevel = $user->karmaLevel; // Highest level achieved (never decreases)

        // Check if user has enough karma for their displayed level
        $hasKarmaForLevel = $userLevel ? $user->karma_points >= $userLevel->required_karma : true;

        $nextLevel = null;
        if ($userLevel !== null) {
            $nextLevel = KarmaLevel::where('required_karma', '>', $userLevel->required_karma)
                ->orderBy('required_karma', 'asc')
                ->first();
        }

        $progressPercentage = 0;
        if ($nextLevel !== null && $userLevel !== null && $hasKarmaForLevel) {
            // Only show progress if user has enough karma for their current level
            $currentLevelKarma = $userLevel->required_karma;
            $nextLevelKarma = $nextLevel->required_karma;
            $karmaNeeded = $nextLevelKarma - $currentLevelKarma;
            $karmaProgress = $user->karma_points - $currentLevelKarma;
            $progressPercentage = min(100, max(0, ($karmaProgress / $karmaNeeded) * 100));
        }

        $karmaHistory = $user->karmaHistory()
            ->latest()
            ->take(10)
            ->get()
            ->map(static fn ($item) => [
                'amount' => $item->amount,
                'source' => $item->source,
                'source_id' => $item->source_id,
                'date' => $item->created_at->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'karma_points' => $user->karma_points,
            'current_level' => $userLevel,
            'next_level' => $nextLevel,
            'progress_percentage' => $progressPercentage,
            'has_karma_for_level' => $hasKarmaForLevel,
            'karma_multiplier' => $user->karma_multiplier,
            'streak' => [
                'current' => $user->streak ? $user->streak->current_streak : 0,
                'longest' => $user->streak ? $user->streak->longest_streak : 0,
                'last_activity' => $user->streak ? $user->streak->last_activity_date->format('Y-m-d') : null,
            ],
            'recent_karma' => $karmaHistory,
        ]);
    }

    public function karmaLevels()
    {
        $levels = KarmaLevel::orderBy('required_karma')->get();

        return response()->json([
            'levels' => $levels,
        ]);
    }

    public function leaderboard(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $timeframe = $request->input('timeframe', 'all');

        $query = User::with('karmaLevel')
            ->orderBy('karma_points', 'desc');

        if ($timeframe === 'week') {
            $query->where('updated_at', '>=', now()->subDays(7));
        } elseif ($timeframe === 'month') {
            $query->where('updated_at', '>=', now()->subDays(30));
        }

        $users = $query->paginate($perPage);

        return response()->json([
            'users' => UserResource::collection($users),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function updateStreak(Request $request)
    {
        $user = $request->user();

        try {
            $streak = $this->karmaService->recordActivity($user);

            return response()->json([
                'streak' => $streak,
                'message' => __('messages.karma.streak_updated'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.karma.streak_update_failed'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    public function achievements(Request $request)
    {
        $user = $request->user();

        $achievements = $this->getUserAchievements($user, includeKarmaBonus: true);

        $groupedAchievements = $achievements->groupBy('type');

        return response()->json([
            'achievements' => $groupedAchievements,
            'unlocked_count' => $achievements->where('unlocked', true)->count(),
            'total_count' => $achievements->count(),
        ]);
    }

    public function moderationHistory(Request $request)
    {
        $user = $request->user();

        // Get active and recent bans
        $bans = $user->bans()
            ->with('bannedByUser')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(static fn ($ban) => [
                'id' => $ban->id,
                'type' => $ban->type,
                'reason' => $ban->reason,
                'is_active' => $ban->is_active,
                'expires_at' => $ban->expires_at?->format('Y-m-d H:i:s'),
                'created_at' => $ban->created_at->format('Y-m-d H:i:s'),
                'banned_by' => $ban->bannedByUser ? [
                    'id' => $ban->bannedByUser->id,
                    'username' => $ban->bannedByUser->username,
                ] : null,
            ]);

        // Get active and recent strikes
        $strikes = $user->strikes()
            ->with('issuedByUser')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(static fn ($strike) => [
                'id' => $strike->id,
                'type' => $strike->type,
                'reason' => $strike->reason,
                'is_active' => $strike->is_active,
                'expires_at' => $strike->expires_at?->format('Y-m-d H:i:s'),
                'created_at' => $strike->created_at->format('Y-m-d H:i:s'),
                'issued_by' => $strike->issuedByUser ? [
                    'id' => $strike->issuedByUser->id,
                    'username' => $strike->issuedByUser->username,
                ] : null,
            ]);

        // Get moderated posts
        $moderatedPosts = $user->posts()
            ->where('status', 'hidden')
            ->with('moderatedBy')
            ->orderBy('moderated_at', 'desc')
            ->get()
            ->map(static fn ($post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'moderation_reason' => $post->moderation_reason,
                'moderated_at' => $post->moderated_at?->format('Y-m-d H:i:s'),
                'moderated_by' => $post->moderatedBy ? [
                    'id' => $post->moderatedBy->id,
                    'username' => $post->moderatedBy->username,
                ] : null,
            ]);

        // Get moderated comments
        $moderatedComments = $user->comments()
            ->where('status', 'hidden')
            ->with(['moderatedBy', 'post'])
            ->orderBy('moderated_at', 'desc')
            ->get()
            ->map(static fn ($comment) => [
                'id' => $comment->id,
                'content' => substr($comment->content, 0, 100) . (strlen($comment->content) > 100 ? '...' : ''),
                'moderation_reason' => $comment->moderation_reason,
                'moderated_at' => $comment->moderated_at?->format('Y-m-d H:i:s'),
                'post' => $comment->post ? [
                    'id' => $comment->post->id,
                    'title' => $comment->post->title,
                    'slug' => $comment->post->slug,
                ] : null,
                'moderated_by' => $comment->moderatedBy ? [
                    'id' => $comment->moderatedBy->id,
                    'username' => $comment->moderatedBy->username,
                ] : null,
            ]);

        return response()->json([
            'bans' => [
                'active' => $bans->where('is_active', true)->values(),
                'history' => $bans->where('is_active', false)->values(),
            ],
            'strikes' => [
                'active' => $strikes->where('is_active', true)->values(),
                'history' => $strikes->where('is_active', false)->values(),
            ],
            'moderated_posts' => $moderatedPosts,
            'moderated_comments' => $moderatedComments,
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // Verify password for security
        $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($request->password, $user->password)) {
            return ApiResponse::error(__('auth.password'), null, 422);
        }

        // Prevent admins from deleting their accounts this way
        if ($user->isAdmin()) {
            return ApiResponse::error(__('auth.admin_cannot_delete'), null, 403);
        }

        // Get next deletion number (use DB query to bypass any model scopes)
        $maxDeletionNumber = DB::table('users')->max('deletion_number');
        $deletionNumber = $maxDeletionNumber ? $maxDeletionNumber + 1 : 1;

        // Anonymize user data (GDPR compliant)
        $user->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'deletion_number' => $deletionNumber,
            'username' => 'deleted_' . $user->id . '_' . time(), // Change username to allow reuse
            'email' => 'deleted+' . $user->id . '@deleted.local',
            'password' => Hash::make(Str::random(64)), // Random unusable password
            'display_name' => null,
            'bio' => null,
            'avatar_url' => null,
            'professional_title' => null,
            'institution' => null,
            'academic_degree' => null,
            'expertise_areas' => null,
            'settings' => null,
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return ApiResponse::success(null, __('auth.account_deleted'));
    }

    /**
     * Search users by username.
     */
    public function searchUsers(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $users = User::where('username', 'like', "%{$query}%")
            ->where('is_guest', false)
            ->select('id', 'username', 'avatar')
            ->limit(10)
            ->get();

        return response()->json(['data' => $users]);
    }

    /**
     * Convert Font Awesome icon class to Iconify format
     * e.g., "fas fa-star" -> "fa6-solid:star"
     * e.g., "far fa-bookmark" -> "fa6-regular:bookmark".
     */
    private function convertIconToIconify(?string $icon): ?string
    {
        if (! $icon) {
            return null;
        }

        // If already in iconify format, return as-is
        if (str_contains($icon, ':')) {
            return $icon;
        }

        // Map of FA5 icon names to FA6 icon names (icons that were renamed)
        // Comprehensive list of common FA5 to FA6 renames
        $iconNameMap = [
            // Common renames
            'external-link-alt' => 'arrow-up-right-from-square',
            'times-circle' => 'circle-xmark',
            'times' => 'xmark',
            'cut' => 'scissors',
            'laugh' => 'face-laugh',
            'edit' => 'pen-to-square',
            'pencil-alt' => 'pen',
            'project-diagram' => 'diagram-project',
            'exclamation-triangle' => 'triangle-exclamation',
            'fist-raised' => 'hand-fist',
            'hand-rock' => 'hand-fist',
            'calendar-alt' => 'calendar-days',
            'comment-dots' => 'comment',
            'file-alt' => 'file-lines',
            'trash-alt' => 'trash-can',
            'sign-in-alt' => 'right-to-bracket',
            'sign-out-alt' => 'right-from-bracket',
            'play-circle' => 'circle-play',
            'pause-circle' => 'circle-pause',
            'stop-circle' => 'circle-stop',
            'sync-alt' => 'rotate',
            'th-large' => 'table-cells-large',
            'undo' => 'rotate-left',
            'redo' => 'rotate-right',
            'user-edit' => 'user-pen',
            'users-cog' => 'users-gear',
            'user-circle' => 'circle-user',
            'cloud-upload-alt' => 'cloud-arrow-up',
            'comment-alt' => 'message',
            'fire-alt' => 'fire-flame-curved',
            'share-alt' => 'share-nodes',
            'shield-alt' => 'shield-halved',
            'search' => 'magnifying-glass',
            'home' => 'house',
            'cog' => 'gear',
            // Additional common renames
            'link' => 'link',  // stays same but document it
            'arrow-alt-circle-up' => 'circle-up',
            'arrow-alt-circle-down' => 'circle-down',
            'arrow-alt-circle-left' => 'circle-left',
            'arrow-alt-circle-right' => 'circle-right',
            'exclamation-circle' => 'circle-exclamation',
            'question-circle' => 'circle-question',
            'info-circle' => 'circle-info',
            'check-circle' => 'circle-check',
            'minus-circle' => 'circle-minus',
            'plus-circle' => 'circle-plus',
            'star-half-alt' => 'star-half-stroke',
            'sort-alpha-down' => 'arrow-down-a-z',
            'sort-alpha-up' => 'arrow-up-a-z',
            'sort-amount-down' => 'arrow-down-wide-short',
            'sort-amount-up' => 'arrow-up-wide-short',
            'sort-numeric-down' => 'arrow-down-1-9',
            'sort-numeric-up' => 'arrow-up-1-9',
            'exchange-alt' => 'arrow-right-arrow-left',
            'long-arrow-alt-down' => 'down-long',
            'long-arrow-alt-left' => 'left-long',
            'long-arrow-alt-right' => 'right-long',
            'long-arrow-alt-up' => 'up-long',
        ];

        // Determine the prefix type (fas, far, fab)
        $prefix = 'fa6-solid'; // default
        if (str_starts_with($icon, 'far ')) {
            $prefix = 'fa6-regular';
            $icon = substr($icon, 4);
        } elseif (str_starts_with($icon, 'fab ')) {
            $prefix = 'fa6-brands';
            $icon = substr($icon, 4);
        } elseif (str_starts_with($icon, 'fas ')) {
            $icon = substr($icon, 4);
        }

        // Remove any remaining fa- prefix
        $icon = str_replace('fa-', '', $icon);
        $icon = trim($icon);

        // Map old icon names to new ones
        if (isset($iconNameMap[$icon])) {
            $icon = $iconNameMap[$icon];
        }

        return $prefix . ':' . $icon;
    }

    /**
     * Get all achievements with user progress (optimized - no N+1).
     */
    private function getUserAchievements(User $user, bool $includeKarmaBonus = false): \Illuminate\Support\Collection
    {
        $userAchievementsMap = $user->achievements()->get()->keyBy('id');

        return Achievement::get()->map(function ($achievement) use ($userAchievementsMap, $includeKarmaBonus) {
            $userAchievement = $userAchievementsMap->get($achievement->id);

            $data = [
                'id' => $achievement->id,
                'key' => $achievement->slug,
                'name' => __($achievement->name),
                'description' => __($achievement->description),
                'icon' => $achievement->icon,
                'icon_iconify' => $this->convertIconToIconify($achievement->icon),
                'type' => $achievement->type,
                'progress' => $userAchievement?->pivot->progress ?? 0,
                'unlocked' => $userAchievement?->pivot->unlocked_at !== null,
                'unlocked_at' => $userAchievement?->pivot->unlocked_at,
            ];

            if ($includeKarmaBonus) {
                $data['karma_bonus'] = $achievement->karma_bonus;
            }

            return $data;
        });
    }
}
