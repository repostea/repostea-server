<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sub;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for handling sub moderation operations.
 */
final class SubModerationService
{
    /**
     * Get all moderators for a sub including the owner.
     *
     * @return Collection<int, User>
     */
    public function getModerators(Sub $sub): Collection
    {
        $moderators = $sub->moderators()
            ->select('users.id', 'users.username', 'users.avatar', 'users.karma_points')
            ->withPivot('is_owner', 'added_by', 'created_at')
            ->orderByPivot('is_owner', 'desc')
            ->orderByPivot('created_at', 'asc')
            ->get();

        // Add the creator if not already in the list
        $creatorInList = $moderators->contains('id', $sub->created_by);
        if (! $creatorInList) {
            $creator = User::select('id', 'username', 'avatar', 'karma_points')->find($sub->created_by);
            if ($creator) {
                $creator->pivot = (object) [
                    'is_owner' => true,
                    'added_by' => null,
                    'created_at' => $sub->created_at,
                ];
                $moderators->prepend($creator);
            }
        }

        return $moderators;
    }

    /**
     * Add a moderator to a sub.
     *
     * @return array{success: bool, message: string, user?: User}
     */
    public function addModerator(Sub $sub, int $userId, User $addedBy): array
    {
        // Can't add the owner as moderator (they already are)
        if ($userId === $sub->created_by) {
            return [
                'success' => false,
                'message' => __('subs.owner_already_moderator'),
            ];
        }

        // Check if user is already a moderator
        if ($sub->moderators()->where('user_id', $userId)->exists()) {
            return [
                'success' => false,
                'message' => __('subs.already_moderator'),
            ];
        }

        // Check if user is a member of the sub
        $isMember = $sub->subscribers()
            ->where('user_id', $userId)
            ->wherePivot('status', 'active')
            ->exists();

        if (! $isMember) {
            return [
                'success' => false,
                'message' => __('subs.must_be_member_to_moderate'),
            ];
        }

        // Add as moderator
        $sub->moderators()->attach($userId, [
            'is_owner' => false,
            'added_by' => $addedBy->id,
        ]);

        $user = User::select('id', 'username', 'avatar', 'karma_points')->find($userId);

        return [
            'success' => true,
            'message' => __('subs.moderator_added'),
            'user' => $user,
        ];
    }

    /**
     * Remove a moderator from a sub.
     *
     * @return array{success: bool, message: string}
     */
    public function removeModerator(Sub $sub, int $userId): array
    {
        // Can't remove the owner
        if ($userId === $sub->created_by) {
            return [
                'success' => false,
                'message' => __('subs.cannot_remove_owner'),
            ];
        }

        $sub->moderators()->detach($userId);

        return [
            'success' => true,
            'message' => __('subs.moderator_removed'),
        ];
    }

    /**
     * Claim ownership of an orphaned sub.
     *
     * @return array{success: bool, message: string, sub?: Sub}
     */
    public function claimOwnership(Sub $sub, User $user): array
    {
        // Check if sub is orphaned
        if (! $sub->isOrphaned()) {
            return [
                'success' => false,
                'message' => __('subs.not_orphaned'),
            ];
        }

        // Check if user can claim
        if (! $sub->canClaimOwnership($user)) {
            return [
                'success' => false,
                'message' => __('subs.cannot_claim'),
            ];
        }

        // Transfer ownership
        $oldOwnerId = $sub->created_by;
        $sub->created_by = $user->id;
        $sub->save();

        // Clear orphaned status
        $sub->clearOrphanedStatus();

        // Add new owner as moderator with is_owner flag
        $sub->moderators()->syncWithoutDetaching([
            $user->id => ['is_owner' => true, 'added_by' => $user->id],
        ]);

        // Remove old owner from moderators if they exist
        if ($oldOwnerId) {
            $sub->moderators()->detach($oldOwnerId);
        }

        return [
            'success' => true,
            'message' => __('subs.ownership_claimed'),
            'sub' => $sub->fresh(['creator']),
        ];
    }

    /**
     * Get claim status information for an orphaned sub.
     *
     * @return array{is_orphaned: bool, can_claim: bool, has_priority: bool, active_moderators: int, grace_period_days_remaining: int, grace_period_expired: bool}
     */
    public function getClaimStatus(Sub $sub, ?User $user): array
    {
        $isOrphaned = $sub->isOrphaned();
        $canClaim = false;
        $hasPriority = false;
        $moderatorCount = 0;
        $gracePeriodDaysRemaining = 0;
        $gracePeriodExpired = false;

        if ($isOrphaned && $user) {
            $canClaim = $sub->canClaimOwnership($user);
            $hasPriority = $sub->hasClaimPriority($user);
            $moderatorCount = $sub->getActiveModeratorCount();
            $gracePeriodDaysRemaining = $sub->getModeratorPriorityDaysRemaining();
            $gracePeriodExpired = $sub->isModeratorGracePeriodExpired();
        }

        return [
            'is_orphaned' => $isOrphaned,
            'can_claim' => $canClaim,
            'has_priority' => $hasPriority,
            'active_moderators' => $moderatorCount,
            'grace_period_days_remaining' => $gracePeriodDaysRemaining,
            'grace_period_expired' => $gracePeriodExpired,
        ];
    }
}
