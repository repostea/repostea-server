<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\KarmaHistory;
use App\Models\ModerationLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserBan;
use App\Models\UserStrike;
use App\Notifications\AchievementUnlocked;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Log;

final class AdminUserController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all users with filters.
     */
    public function index(Request $request)
    {
        $this->authorize('access-admin');

        $query = User::with(['roles', 'bans' => fn ($q) => $q->where('is_active', true)])
            ->withCount(['posts', 'comments', 'votes']);

        // Search (case-insensitive)
        if ($request->has('search') && ! empty($request->get('search'))) {
            $search = strtolower($request->get('search'));
            $query->where(function ($q) use ($search): void {
                $q->whereRaw('LOWER(username) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        // Filter by ban status
        if ($request->has('banned') && $request->get('banned') === '1') {
            $query->whereHas('bans', fn ($q) => $q->where('is_active', true));
        }

        // Filter by role
        if ($request->has('role') && ! empty($request->get('role'))) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $request->get('role')));
        }

        // Filter by email verification status
        if ($request->has('verified')) {
            if ($request->get('verified') === '1') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->get('verified') === '0') {
                $query->whereNull('email_verified_at');
            }
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        // Validate sort direction
        if (! in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        // Apply sorting based on column
        switch ($sortBy) {
            case 'username':
                $query->orderBy('username', $sortDirection);
                break;
            case 'karma':
                $query->orderBy('karma_points', $sortDirection);
                break;
            case 'status':
                // Sort by banned status (users with active bans first/last)
                $query->leftJoin('user_bans', function ($join): void {
                    $join->on('users.id', '=', 'user_bans.user_id')
                        ->where('user_bans.is_active', '=', true);
                })
                    ->select('users.*')
                    ->selectRaw('CASE WHEN user_bans.id IS NOT NULL THEN 1 ELSE 0 END as is_banned')
                    ->orderBy('is_banned', $sortDirection)
                    ->orderBy('created_at', 'desc'); // Secondary sort by creation date
                break;
            case 'created_at':
            default:
                $query->orderBy('created_at', $sortDirection);
                break;
        }

        $users = $query->paginate(50)->appends($request->except('page'));

        // Get only admin and moderator roles for the filter dropdown
        $roles = Role::whereIn('slug', ['admin', 'moderator'])->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show user details and moderation history.
     */
    public function show(User $user)
    {
        $this->authorize('access-admin');

        $user->load([
            'roles',
            'bans' => fn ($q) => $q->latest(),
            'strikes' => fn ($q) => $q->latest(),
            'posts' => fn ($q) => $q->latest()->limit(10),
            'comments' => fn ($q) => $q->latest()->limit(10),
            'achievements',
        ]);

        $moderationLogs = ModerationLog::where('target_user_id', $user->id)
            ->with('moderator')
            ->latest('created_at')
            ->paginate(20);

        // Get email change history
        $emailChanges = ModerationLog::where('target_user_id', $user->id)
            ->where('action', 'email_changed')
            ->latest('created_at')
            ->get();

        // Only allow assigning moderator and admin roles
        $allRoles = Role::whereIn('slug', ['moderator', 'admin'])->get();

        // Get achievements for assignment
        // Special: Only collaborator achievements (manual assignment)
        $collaboratorAchievements = Achievement::where('slug', 'like', 'collaborator_%')->orderBy('slug')->get();
        // Normal: All other achievements (mostly automatic)
        $normalAchievements = Achievement::where('slug', 'not like', 'collaborator_%')->orderBy('type')->orderBy('name')->get();

        return view('admin.users.show', compact('user', 'moderationLogs', 'allRoles', 'collaboratorAchievements', 'normalAchievements', 'emailChanges'));
    }

    /**
     * Ban a user.
     */
    public function ban(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'type' => 'required|in:temporary,permanent,shadowban',
            'reason' => 'required|string|max:1000',
            'internal_notes' => 'nullable|string|max:2000',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        $expiresAt = null;
        if ($validated['type'] === 'temporary' && isset($validated['duration_days']) && $validated['duration_days'] !== '') {
            $expiresAt = now()->addDays((int) $validated['duration_days']);
        }

        $ban = UserBan::create([
            'user_id' => $user->id,
            'banned_by' => Auth::id(),
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'internal_notes' => $validated['internal_notes'] ?? null,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        // Log the action
        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'ban_user',
            targetUserId: $user->id,
            reason: $validated['reason'],
            metadata: [
                'ban_id' => $ban->id,
                'type' => $validated['type'],
                'expires_at' => $expiresAt?->toDateTimeString(),
            ],
        );

        return redirect()->back()->with('success', "User {$user->username} has been banned.");
    }

    /**
     * Unban a user.
     */
    public function unban(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $activeBan = $user->bans()->where('is_active', true)->first();

        if (! $activeBan) {
            return redirect()->back()->with('error', 'User is not banned.');
        }

        $activeBan->update(['is_active' => false]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'unban_user',
            targetUserId: $user->id,
            metadata: ['ban_id' => $activeBan->id],
        );

        return redirect()->back()->with('success', "User {$user->username} has been unbanned.");
    }

    /**
     * Permanently delete a user and all their data.
     * Only admins can perform this action.
     */
    public function destroy(User $user)
    {
        $this->authorize('admin-only');

        // Prevent deleting yourself
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        // Prevent deleting other admins
        if ($user->hasRole('admin')) {
            return redirect()->back()->with('error', 'You cannot delete other administrator accounts.');
        }

        $username = $user->username;

        try {
            DB::beginTransaction();

            // Log the deletion before actually deleting
            ModerationLog::logAction(
                moderatorId: Auth::id(),
                action: 'delete_user',
                targetUserId: $user->id,
                metadata: [
                    'username' => $username,
                    'email' => $user->email,
                    'posts_count' => $user->posts()->count(),
                    'comments_count' => $user->comments()->count(),
                ],
            );

            // Delete the user (cascade will handle related data based on DB constraints)
            $user->delete();

            DB::commit();

            return redirect()->route('admin.users')->with('success', "User {$username} has been deleted. Can be restored within 15 days from Deleted Users.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting user: ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while deleting the user. Please try again.');
        }
    }

    /**
     * Show deleted users.
     */
    public function deleted(Request $request)
    {
        $this->authorize('admin-only');

        $query = User::onlyTrashed();

        // Search
        if ($request->has('search') && ! empty($request->get('search'))) {
            $search = strtolower($request->get('search'));
            $query->where(function ($q) use ($search): void {
                $q->whereRaw('LOWER(username) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        $users = $query->orderBy('deleted_at', 'desc')->paginate(50)->appends($request->except('page'));

        return view('admin.users.deleted', compact('users'));
    }

    /**
     * Restore a deleted user.
     */
    public function restore(int $id)
    {
        $this->authorize('admin-only');

        $user = User::onlyTrashed()->findOrFail($id);
        $username = $user->username;

        $user->restore();

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'restore_user',
            targetUserId: $user->id,
            metadata: ['username' => $username],
        );

        return redirect()->route('admin.users.deleted')->with('success', "User {$username} has been restored successfully.");
    }

    /**
     * Permanently delete a user (force delete).
     */
    public function forceDelete(int $id)
    {
        $this->authorize('admin-only');

        $user = User::onlyTrashed()->findOrFail($id);
        $username = $user->username;

        try {
            DB::beginTransaction();

            // Log before permanent deletion
            ModerationLog::logAction(
                moderatorId: Auth::id(),
                action: 'force_delete_user',
                targetUserId: $user->id,
                metadata: [
                    'username' => $username,
                    'email' => $user->email,
                    'deleted_at' => $user->deleted_at->toDateTimeString(),
                ],
            );

            // Permanent delete
            $user->forceDelete();

            DB::commit();

            return redirect()->route('admin.users.deleted')->with('success', "User {$username} has been permanently deleted.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error force deleting user: ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while permanently deleting the user.');
        }
    }

    /**
     * Give a strike to a user.
     */
    public function giveStrike(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'type' => 'required|in:warning,minor,major,critical',
            'reason' => 'required|string|max:1000',
            'internal_notes' => 'nullable|string|max:2000',
            'related_post_id' => 'nullable|exists:posts,id',
            'related_comment_id' => 'nullable|exists:comments,id',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        $expiresAt = null;
        if (isset($validated['duration_days']) && $validated['duration_days'] !== '') {
            $expiresAt = now()->addDays((int) $validated['duration_days']);
        }

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => Auth::id(),
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'internal_notes' => $validated['internal_notes'] ?? null,
            'related_post_id' => $validated['related_post_id'] ?? null,
            'related_comment_id' => $validated['related_comment_id'] ?? null,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'give_strike',
            targetUserId: $user->id,
            reason: $validated['reason'],
            metadata: [
                'strike_id' => $strike->id,
                'type' => $validated['type'],
                'expires_at' => $expiresAt?->toDateTimeString(),
            ],
        );

        return redirect()->back()->with('success', "Strike given to user {$user->username}.");
    }

    /**
     * Remove a strike from a user.
     */
    public function removeStrike(Request $request, UserStrike $strike)
    {
        $this->authorize('access-admin');

        $strike->update(['is_active' => false]);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'remove_strike',
            targetUserId: $strike->user_id,
            metadata: ['strike_id' => $strike->id],
        );

        return redirect()->back()->with('success', 'Strike has been removed.');
    }

    /**
     * Edit a ban.
     */
    public function editBan(Request $request, UserBan $ban)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'type' => 'required|in:temporary,permanent,shadowban',
            'reason' => 'required|string|max:1000',
            'internal_notes' => 'nullable|string|max:2000',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        // Store old values for logging
        $oldValues = [
            'type' => $ban->type,
            'reason' => $ban->reason,
            'internal_notes' => $ban->internal_notes,
            'expires_at' => $ban->expires_at?->toDateTimeString(),
        ];

        // Calculate new expiration
        $expiresAt = null;
        if ($validated['type'] === 'temporary' && isset($validated['duration_days']) && $validated['duration_days'] !== '') {
            $expiresAt = now()->addDays((int) $validated['duration_days']);
        }

        // Update ban
        $ban->update([
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'internal_notes' => $validated['internal_notes'] ?? null,
            'expires_at' => $expiresAt,
        ]);

        // Store new values for logging
        $newValues = [
            'type' => $ban->type,
            'reason' => $ban->reason,
            'internal_notes' => $ban->internal_notes,
            'expires_at' => $ban->expires_at?->toDateTimeString(),
        ];

        // Log the edit action
        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'edit_ban',
            targetUserId: $ban->user_id,
            reason: 'Ban modified',
            metadata: [
                'ban_id' => $ban->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ],
        );

        return redirect()->back()->with('success', 'Ban has been updated.');
    }

    /**
     * Edit a strike.
     */
    public function editStrike(Request $request, UserStrike $strike)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'type' => 'required|in:warning,minor,major,critical',
            'reason' => 'required|string|max:1000',
            'internal_notes' => 'nullable|string|max:2000',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        // Store old values for logging
        $oldValues = [
            'type' => $strike->type,
            'reason' => $strike->reason,
            'internal_notes' => $strike->internal_notes,
            'expires_at' => $strike->expires_at?->toDateTimeString(),
        ];

        // Calculate new expiration
        $expiresAt = null;
        if (isset($validated['duration_days']) && $validated['duration_days'] !== '') {
            $expiresAt = now()->addDays((int) $validated['duration_days']);
        }

        // Update strike
        $strike->update([
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'internal_notes' => $validated['internal_notes'] ?? null,
            'expires_at' => $expiresAt,
        ]);

        // Store new values for logging
        $newValues = [
            'type' => $strike->type,
            'reason' => $strike->reason,
            'internal_notes' => $strike->internal_notes,
            'expires_at' => $strike->expires_at?->toDateTimeString(),
        ];

        // Log the edit action
        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'edit_strike',
            targetUserId: $strike->user_id,
            reason: 'Strike modified',
            metadata: [
                'strike_id' => $strike->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ],
        );

        return redirect()->back()->with('success', 'Strike has been updated.');
    }

    /**
     * Update user invitation limit.
     */
    public function updateInvitationLimit(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'invitation_limit' => 'required|integer|min:0|max:10000',
        ]);

        $oldLimit = $user->invitation_limit ?? 'default (' . $user->getInvitationLimit() . ')';

        $user->update([
            'invitation_limit' => $validated['invitation_limit'],
        ]);

        $newLimit = $validated['invitation_limit'];

        return redirect()->back()->with('success', "Invitation limit updated from {$oldLimit} to {$newLimit}.");
    }

    /**
     * Reset user invitation limit to default (karma-based).
     */
    public function resetInvitationLimit(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $user->update([
            'invitation_limit' => null,
        ]);

        $karmaBasedLimit = $user->getInvitationLimit();

        return redirect()->back()->with('success', "Invitation limit reset to default (karma-based: {$karmaBasedLimit}).");
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(Request $request, User $user)
    {
        $this->authorize('admin-only');

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::find($validated['role_id']);

        if ($user->roles()->where('role_id', $role->id)->exists()) {
            return redirect()->back()->with('error', "User already has the {$role->name} role.");
        }

        $user->roles()->attach($role->id);

        return redirect()->back()->with('success', "Role '{$role->name}' assigned successfully.");
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(Request $request, User $user)
    {
        $this->authorize('admin-only');

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::find($validated['role_id']);

        if (! $user->roles()->where('role_id', $role->id)->exists()) {
            return redirect()->back()->with('error', "User doesn't have the {$role->name} role.");
        }

        $user->roles()->detach($role->id);

        return redirect()->back()->with('success', "Role '{$role->name}' removed successfully.");
    }

    /**
     * Toggle a special permission for a user.
     */
    public function togglePermission(Request $request, User $user): RedirectResponse
    {
        $this->authorize('admin-only');

        $validated = $request->validate([
            'permission' => 'required|in:can_create_subs',
        ]);

        $permission = $validated['permission'];
        $newValue = ! $user->$permission;
        $user->$permission = $newValue;
        $user->save();

        $permissionLabel = match ($permission) {
            'can_create_subs' => 'Create Communities',
            default => $permission,
        };

        $status = $newValue ? 'enabled' : 'disabled';

        return redirect()->back()->with('success', "Permission '{$permissionLabel}' {$status} for {$user->username}.");
    }

    /**
     * Show karma history audit log.
     */
    public function karmaHistory(Request $request)
    {
        $this->authorize('access-admin');

        $query = KarmaHistory::with('user:id,username,email,karma_points')
            ->orderBy('created_at', 'desc');

        // Filter by user
        $userKarma = null;
        if ($request->filled('user')) {
            $query->whereHas('user', function ($q) use ($request): void {
                $q->where('username', 'like', '%' . $request->user . '%');
            });

            // Get user karma summary if filtering by single user
            $user = User::where('username', 'like', '%' . $request->user . '%')->first();

            if ($user) {
                $userKarma = [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'current_karma' => $user->karma_points ?? 0,
                ];
            }
        }

        // Filter by amount type (positive/negative)
        if ($request->filled('type')) {
            if ($request->type === 'positive') {
                $query->where('amount', '>', 0);
            } elseif ($request->type === 'negative') {
                $query->where('amount', '<', 0);
            }
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $history = $query->paginate(50);

        // Statistics
        $stats = [
            'total_transactions' => KarmaHistory::count(),
            'total_positive' => KarmaHistory::where('amount', '>', 0)->sum('amount'),
            'total_negative' => KarmaHistory::where('amount', '<', 0)->sum('amount'),
            'unique_users' => KarmaHistory::distinct('user_id')->count('user_id'),
        ];

        return view('admin.karma-history', compact('history', 'stats', 'userKarma'));
    }

    /**
     * Assign achievement to a user (admin only).
     */
    public function assignAchievement(Request $request, User $user)
    {
        $this->authorize('admin-only');

        $validated = $request->validate([
            'achievement_id' => 'required|exists:achievements,id',
        ]);

        $achievement = Achievement::findOrFail($validated['achievement_id']);

        // Check if user already has this achievement
        if ($user->achievements()->where('achievement_id', $achievement->id)->exists()) {
            return redirect()
                ->route('admin.users.show', $user)
                ->with('error', 'User already has this achievement.');
        }

        // Assign the achievement
        $user->achievements()->attach($achievement->id, [
            'unlocked_at' => now(),
            'progress' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Grant karma bonus
        if ($achievement->karma_bonus > 0) {
            $user->increment('karma_points', $achievement->karma_bonus);

            // Log karma change
            KarmaHistory::create([
                'user_id' => $user->id,
                'amount' => $achievement->karma_bonus,
                'source' => 'achievement',
                'source_id' => $achievement->id,
                'description' => "Achievement earned: {$achievement->name}",
            ]);
        }

        // Send notification
        $user->notify(new AchievementUnlocked($achievement));

        // Log admin action
        ModerationLog::create([
            'moderator_id' => auth()->id(),
            'action' => 'assign_achievement',
            'target_type' => 'user',
            'target_user_id' => $user->id,
            'details' => "Manually assigned achievement: {$achievement->name}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', "Achievement '{$achievement->name}' assigned successfully!");
    }

    /**
     * Remove achievement from a user (admin only).
     */
    public function removeAchievement(User $user, Achievement $achievement)
    {
        $this->authorize('admin-only');

        // Check if user has this achievement
        if (! $user->achievements()->where('achievement_id', $achievement->id)->exists()) {
            return redirect()
                ->route('admin.users.show', $user)
                ->with('error', 'User does not have this achievement.');
        }

        // Remove the achievement
        $user->achievements()->detach($achievement->id);

        // Deduct karma bonus
        if ($achievement->karma_bonus > 0) {
            $user->decrement('karma_points', $achievement->karma_bonus);

            // Log karma change
            KarmaHistory::create([
                'user_id' => $user->id,
                'amount' => -$achievement->karma_bonus,
                'source' => 'achievement',
                'source_id' => $achievement->id,
                'description' => "Achievement removed: {$achievement->name}",
            ]);
        }

        // Log admin action
        ModerationLog::create([
            'moderator_id' => auth()->id(),
            'action' => 'remove_achievement',
            'target_type' => 'user',
            'target_user_id' => $user->id,
            'details' => "Manually removed achievement: {$achievement->name}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', "Achievement '{$achievement->name}' removed successfully!");
    }
}
