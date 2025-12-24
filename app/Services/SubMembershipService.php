<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\SubMemberJoined;
use App\Models\Sub;
use App\Models\User;
use App\Notifications\MembershipRequestReceived;
use App\Notifications\SubOrphaned;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Service for handling sub membership operations.
 */
final class SubMembershipService
{
    public function __construct(
        private readonly AchievementService $achievementService,
    ) {}

    /**
     * Join a sub or request membership for private subs.
     *
     * @return array{success: bool, message: string, is_member: bool, request_pending: bool, member_count: int}
     */
    public function join(Sub $sub, User $user, ?string $message = null): array
    {
        // Check if already a member or has pending request
        $existingSubscription = $sub->subscribers()
            ->where('user_id', $user->id)
            ->withPivot('status')
            ->first();

        if ($existingSubscription) {
            $status = $existingSubscription->pivot->status ?? 'active';
            if ($status === 'active') {
                return [
                    'success' => true,
                    'message' => __('subs.already_member'),
                    'is_member' => true,
                    'request_pending' => false,
                    'member_count' => $sub->members_count,
                ];
            }
            if ($status === 'pending') {
                return [
                    'success' => true,
                    'message' => __('subs.request_pending'),
                    'is_member' => false,
                    'request_pending' => true,
                    'member_count' => $sub->members_count,
                ];
            }
        }

        // For private subs, create a pending request
        if ($sub->is_private) {
            return $this->createMembershipRequest($sub, $user, $message);
        }

        // Join directly for public subs
        return $this->addMember($sub, $user);
    }

    /**
     * Create a pending membership request for a private sub.
     *
     * @return array{success: bool, message: string, is_member: bool, request_pending: bool, member_count: int}
     */
    private function createMembershipRequest(Sub $sub, User $user, ?string $message): array
    {
        $sub->subscribers()->attach($user->id, [
            'status' => 'pending',
            'request_message' => $message,
        ]);

        // Notify the sub creator
        $creator = User::find($sub->created_by);
        if ($creator) {
            $creator->notify(new MembershipRequestReceived($sub, $user, $message));
        }

        return [
            'success' => true,
            'message' => __('subs.request_sent'),
            'is_member' => false,
            'request_pending' => true,
            'member_count' => $sub->members_count,
        ];
    }

    /**
     * Add a user as an active member.
     *
     * @return array{success: bool, message: string, is_member: bool, request_pending: bool, member_count: int}
     */
    public function addMember(Sub $sub, User $user): array
    {
        $sub->subscribers()->attach($user->id, ['status' => 'active']);

        $count = $this->updateMemberCount($sub);

        event(new SubMemberJoined($sub, $user));

        $this->achievementService->checkSubMemberAchievements($sub);

        return [
            'success' => true,
            'message' => __('subs.joined'),
            'is_member' => true,
            'request_pending' => false,
            'member_count' => $count,
        ];
    }

    /**
     * Leave a sub.
     *
     * @return array{success: bool, message: string, is_member: bool, member_count: int}
     */
    public function leave(Sub $sub, User $user): array
    {
        if (! $sub->subscribers()->where('user_id', $user->id)->exists()) {
            return [
                'success' => false,
                'message' => __('subs.not_member'),
                'is_member' => false,
                'member_count' => $sub->members_count,
            ];
        }

        $wasOwner = (int) $sub->created_by === (int) $user->id;

        $sub->subscribers()->detach($user->id);
        $sub->moderators()->detach($user->id);

        $count = $this->updateMemberCount($sub);

        if ($wasOwner) {
            $this->handleOwnerLeft($sub, $user);
        }

        return [
            'success' => true,
            'message' => __('subs.left'),
            'is_member' => false,
            'member_count' => $count,
        ];
    }

    /**
     * Handle when the owner leaves the sub.
     */
    private function handleOwnerLeft(Sub $sub, User $previousOwner): void
    {
        $sub->markAsOrphaned();

        $moderators = $sub->moderators()
            ->where('user_id', '!=', $previousOwner->id)
            ->get();

        foreach ($moderators as $moderator) {
            $moderator->notify(new SubOrphaned($sub, $previousOwner));
        }
    }

    /**
     * Get paginated list of members.
     */
    public function getMembers(Sub $sub, int $perPage = 50): LengthAwarePaginator
    {
        return $sub->subscribers()
            ->wherePivot('status', 'active')
            ->select('users.id', 'users.username', 'users.avatar', 'users.karma_points', 'sub_subscriptions.created_at as joined_at')
            ->orderBy('sub_subscriptions.created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Check if a user is an active member of a sub.
     */
    public function isActiveMember(Sub $sub, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $sub->subscribers()
            ->where('user_id', $user->id)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Remove a member from the sub.
     *
     * @return array{success: bool, message: string, member_count?: int}
     */
    public function removeMember(Sub $sub, int $userId): array
    {
        // Can't remove the creator
        if ($userId === $sub->created_by) {
            return [
                'success' => false,
                'message' => __('subs.cannot_remove_creator'),
            ];
        }

        $sub->subscribers()->detach($userId);
        $count = $this->updateMemberCount($sub);

        return [
            'success' => true,
            'message' => __('subs.member_removed'),
            'member_count' => $count,
        ];
    }

    /**
     * Get pending membership requests.
     */
    public function getPendingRequests(Sub $sub, int $perPage = 50): LengthAwarePaginator
    {
        return $sub->subscribers()
            ->wherePivot('status', 'pending')
            ->withPivot('status', 'request_message', 'created_at')
            ->select('users.id', 'users.username', 'users.avatar', 'users.karma_points')
            ->orderBy('sub_subscriptions.created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Approve a pending membership request.
     *
     * @return array{success: bool, message: string, member_count?: int}
     */
    public function approveMembershipRequest(Sub $sub, int $userId): array
    {
        $subscription = $sub->subscribers()
            ->where('user_id', $userId)
            ->wherePivot('status', 'pending')
            ->first();

        if (! $subscription) {
            return [
                'success' => false,
                'message' => __('subs.request_not_found'),
            ];
        }

        $sub->subscribers()->updateExistingPivot($userId, ['status' => 'active']);
        $count = $this->updateMemberCount($sub);

        $user = User::find($userId);
        if ($user) {
            event(new SubMemberJoined($sub, $user));
            $this->achievementService->checkSubMemberAchievements($sub);
        }

        return [
            'success' => true,
            'message' => __('subs.request_approved'),
            'member_count' => $count,
        ];
    }

    /**
     * Reject a pending membership request.
     *
     * @return array{success: bool, message: string}
     */
    public function rejectMembershipRequest(Sub $sub, int $userId): array
    {
        $subscription = $sub->subscribers()
            ->where('user_id', $userId)
            ->wherePivot('status', 'pending')
            ->first();

        if (! $subscription) {
            return [
                'success' => false,
                'message' => __('subs.request_not_found'),
            ];
        }

        $sub->subscribers()->detach($userId);

        return [
            'success' => true,
            'message' => __('subs.request_rejected'),
        ];
    }

    /**
     * Update the member count for a sub.
     */
    private function updateMemberCount(Sub $sub): int
    {
        $count = $sub->subscribers()->where('sub_subscriptions.status', 'active')->count();
        $sub->update(['members_count' => $count]);

        return $count;
    }
}
