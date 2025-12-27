<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentWithPostResource;
use App\Http\Resources\PostCollection;
use App\Http\Resources\UserResource;
use App\Models\Achievement;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for public user profile operations.
 * Authenticated user profile management is in UserController.
 */
final class UserProfileController extends Controller
{
    /**
     * Get user profile by username.
     */
    public function getByUsername(string $username): JsonResponse
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

    /**
     * Get user's public posts.
     */
    public function getUserPosts(string $username): PostCollection|JsonResponse
    {
        $user = User::where('username', $username)->withTrashed()->first();

        if (! $user || $user->deleted_at) {
            return response()->json([
                'message' => __('messages.users.not_found_or_deleted'),
            ], 404);
        }
        $posts = $user->posts()
            ->where('is_anonymous', false)
            ->where('status', Post::STATUS_PUBLISHED)
            ->with(['tags', 'user', 'sub'])
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 15));

        return new PostCollection($posts);
    }

    /**
     * Get user's public comments.
     */
    public function getUserComments(string $username): array|JsonResponse
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
            ->withCount('votes')
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

    /**
     * Search users by username.
     */
    public function searchUsers(Request $request): JsonResponse
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
     * Convert Font Awesome icon class to Iconify format.
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
        $iconNameMap = [
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
            'link' => 'link',
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
    private function getUserAchievements(User $user): \Illuminate\Support\Collection
    {
        $userAchievementsMap = $user->achievements()->get()->keyBy('id');

        return Achievement::get()->map(function ($achievement) use ($userAchievementsMap) {
            $userAchievement = $userAchievementsMap->get($achievement->id);

            return [
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
        });
    }
}
