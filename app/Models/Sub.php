<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $created_by
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property string|null $rules
 * @property string|null $icon
 * @property string $color
 * @property int $members_count
 * @property \Illuminate\Support\Carbon|null $orphaned_at
 * @property-read int|null $posts_count
 * @property bool $is_private
 * @property bool $is_adult
 * @property bool $is_featured
 * @property bool $require_approval
 * @property bool $hide_owner
 * @property bool $hide_moderators
 * @property array<array-key, mixed>|null $allowed_content_types
 * @property string $visibility
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $activeSubscribers
 * @property-read int|null $active_subscribers_count
 * @property-read User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $moderators
 * @property-read int|null $moderators_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Post> $posts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $subscribers
 * @property-read int|null $subscribers_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereAllowedContentTypes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereHideModerators($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereHideOwner($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereIsAdult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereIsFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereIsPrivate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereMembersCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereOrphanedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub wherePostsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereRequireApproval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sub withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class Sub extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'rules',
        'icon',
        'color',
        'members_count',
        'posts_count',
        'is_private',
        'is_adult',
        'is_featured',
        'require_approval',
        'hide_owner',
        'hide_moderators',
        'allowed_content_types',
        'visibility',
        'created_by',
        'orphaned_at',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_adult' => 'boolean',
        'is_featured' => 'boolean',
        'require_approval' => 'boolean',
        'hide_owner' => 'boolean',
        'hide_moderators' => 'boolean',
        'allowed_content_types' => 'array',
        'members_count' => 'integer',
        'posts_count' => 'integer',
        'orphaned_at' => 'datetime',
    ];

    /**
     * Days of grace period for moderators to claim ownership before regular members can.
     */
    public const MODERATOR_CLAIM_PRIORITY_DAYS = 7;

    /**
     * Creator of the sub.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Posts belonging to this sub.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Users subscribed to this sub.
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sub_subscriptions')
            ->withPivot('status', 'request_message')
            ->withTimestamps();
    }

    /**
     * Active members of the sub.
     */
    public function activeSubscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sub_subscriptions')
            ->wherePivot('status', 'active')
            ->withTimestamps();
    }

    /**
     * Check if user is subscribed to this sub.
     */
    public function isSubscribedBy(User $user): bool
    {
        return $this->subscribers()->where('user_id', $user->id)->exists();
    }

    /**
     * Sub moderators.
     */
    public function moderators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sub_moderators')
            ->withPivot('is_owner', 'added_by')
            ->withTimestamps();
    }

    /**
     * Check if user is a moderator of this sub.
     */
    public function isModerator(User $user): bool
    {
        // Creator is always a moderator
        if ($this->created_by === $user->id) {
            return true;
        }

        return $this->moderators()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if user is the owner/creator of the sub.
     */
    public function isOwner(User $user): bool
    {
        return $this->created_by === $user->id;
    }

    /**
     * Check if sub is orphaned (no active owner).
     * A sub is orphaned if orphaned_at is set, or if creator is no longer a member.
     */
    public function isOrphaned(): bool
    {
        // If orphaned_at is set, the sub is orphaned
        if ($this->orphaned_at !== null) {
            return true;
        }

        // Check if creator exists and is still a member
        $creatorIsMember = $this->subscribers()
            ->where('user_id', $this->created_by)
            ->wherePivot('status', 'active')
            ->exists();

        return ! $creatorIsMember;
    }

    /**
     * Check if the moderator priority grace period has expired.
     */
    public function isModeratorGracePeriodExpired(): bool
    {
        if ($this->orphaned_at === null) {
            return false;
        }

        return $this->orphaned_at->addDays(self::MODERATOR_CLAIM_PRIORITY_DAYS)->isPast();
    }

    /**
     * Get remaining days for moderator priority.
     */
    public function getModeratorPriorityDaysRemaining(): int
    {
        if ($this->orphaned_at === null) {
            return 0;
        }

        $expiresAt = $this->orphaned_at->addDays(self::MODERATOR_CLAIM_PRIORITY_DAYS);
        if ($expiresAt->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($expiresAt, false);
    }

    /**
     * Check if user can claim ownership of the sub.
     * Priority: moderators have 7 days exclusivity, then any member can claim.
     */
    public function canClaimOwnership(User $user): bool
    {
        // Sub must be orphaned
        if (! $this->isOrphaned()) {
            return false;
        }

        // User must be a member
        $isMember = $this->subscribers()
            ->where('user_id', $user->id)
            ->wherePivot('status', 'active')
            ->exists();

        if (! $isMember) {
            return false;
        }

        // If user is a moderator, they can always claim
        if ($this->hasClaimPriority($user)) {
            return true;
        }

        // If there are active moderators and grace period hasn't expired, regular members can't claim
        if ($this->getActiveModeratorCount() > 0 && ! $this->isModeratorGracePeriodExpired()) {
            return false;
        }

        return true;
    }

    /**
     * Check if user has claim priority (is a moderator).
     */
    public function hasClaimPriority(User $user): bool
    {
        return $this->moderators()->where('user_id', $user->id)->exists();
    }

    /**
     * Get active moderator count (excluding original owner).
     */
    public function getActiveModeratorCount(): int
    {
        return $this->moderators()
            ->where('user_id', '!=', $this->created_by)
            ->count();
    }

    /**
     * Mark the sub as orphaned.
     */
    public function markAsOrphaned(): void
    {
        $this->update(['orphaned_at' => now()]);
    }

    /**
     * Clear the orphaned status (when ownership is claimed).
     */
    public function clearOrphanedStatus(): void
    {
        $this->update(['orphaned_at' => null]);
    }
}
