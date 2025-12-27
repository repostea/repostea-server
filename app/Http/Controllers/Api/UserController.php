<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Achievement;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Str;

/**
 * API controller for authenticated user profile management.
 * Public profile operations are in UserProfileController.
 */
final class UserController extends Controller
{
    /**
     * Get current user's profile with achievements.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->loadCount(['posts', 'comments', 'votes']);
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

    /**
     * Update current user's profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
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
            $user->avatarUrl = $data['avatar_url'];
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
        $user->loadCount(['posts', 'comments', 'votes']);

        return response()->json([
            'message' => __('messages.profile.updated'),
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Get user's moderation history (bans, strikes, moderated content).
     */
    public function moderationHistory(Request $request): JsonResponse
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
            ->where('status', Post::STATUS_HIDDEN)
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
            ->where('status', Comment::STATUS_HIDDEN)
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

    /**
     * Delete user's account (GDPR compliant).
     */
    public function deleteAccount(Request $request): JsonResponse
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

        // Get next deletion number (include soft-deleted users)
        $maxDeletionNumber = User::withTrashed()->max('deletion_number');
        $deletionNumber = $maxDeletionNumber ? $maxDeletionNumber + 1 : 1;

        // Anonymize user data (GDPR compliant)
        $user->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'deletion_number' => $deletionNumber,
            'username' => 'deleted_' . $user->id . '_' . time(),
            'email' => 'deleted+' . $user->id . '@deleted.local',
            'password' => Hash::make(Str::random(64)),
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
     * Convert Font Awesome icon class to Iconify format.
     */
    private function convertIconToIconify(?string $icon): ?string
    {
        if (! $icon) {
            return null;
        }

        if (str_contains($icon, ':')) {
            return $icon;
        }

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

        $prefix = 'fa6-solid';
        if (str_starts_with($icon, 'far ')) {
            $prefix = 'fa6-regular';
            $icon = substr($icon, 4);
        } elseif (str_starts_with($icon, 'fab ')) {
            $prefix = 'fa6-brands';
            $icon = substr($icon, 4);
        } elseif (str_starts_with($icon, 'fas ')) {
            $icon = substr($icon, 4);
        }

        $icon = str_replace('fa-', '', $icon);
        $icon = trim($icon);

        if (isset($iconNameMap[$icon])) {
            $icon = $iconNameMap[$icon];
        }

        return $prefix . ':' . $icon;
    }

    /**
     * Get all achievements with user progress.
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
