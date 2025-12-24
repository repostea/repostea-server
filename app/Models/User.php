<?php

declare(strict_types=1);

namespace App\Models;

use const PHP_INT_MAX;

use App\Notifications\EmailVerificationNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * @property int $id
 * @property string $username
 * @property string $email
 * @property int|null $karma_points
 * @property int|null $highest_level_id
 * @property string|null $status
 * @property bool $is_guest
 * @property bool $is_deleted
 * @property int|null $deletion_number
 * @property int|null $avatar_image_id
 * @property string|null $federated_id
 * @property string|null $federated_instance
 * @property string|null $federated_username
 * @property int|null $telegram_id
 * @property string|null $pending_email
 * @property string|null $email_change_token
 * @property \Illuminate\Support\Carbon|null $email_change_requested_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read UserPreference|null $preferences
 * @property-read KarmaLevel|null $currentLevel
 * @property-read UserStreak|null $streak
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Post> $posts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $comments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Vote> $votes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Achievement> $achievements
 * @property int|null $period_karma Dynamic property from ranking queries
 * @property float $karma_multiplier
 * @property string $display_name
 * @property \Illuminate\Support\Carbon|null $federated_account_created_at
 * @property string|null $telegram_username
 * @property string|null $telegram_photo_url
 * @property string|null $bio
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $rejection_reason
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $invitation_limit
 * @property string $locale
 * @property string|null $avatar
 * @property string|null $avatar_url
 * @property array<array-key, mixed>|null $settings
 * @property bool $is_verified_expert
 * @property bool $can_create_subs
 * @property string|null $expertise_areas
 * @property string|null $credentials
 * @property string|null $institution
 * @property string|null $professional_title
 * @property string|null $academic_degree
 * @property string|null $publications
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read int|null $achievements_count
 * @property-read Image|null $avatarImage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserBan> $bans
 * @property-read int|null $bans_count
 * @property-read int|null $comments_count
 * @property-read mixed $favorites_list
 * @property-read string|null $federated_handle
 * @property-read mixed $read_later_list
 * @property-read KarmaLevel|null $highestLevel
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, KarmaHistory> $karmaHistory
 * @property-read int|null $karma_history_count
 * @property-read KarmaLevel|null $karmaLevel
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read int|null $posts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \NotificationChannels\WebPush\PushSubscription> $pushSubscriptions
 * @property-read int|null $push_subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SavedList> $savedLists
 * @property-read int|null $saved_lists_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SealMark> $sealMarks
 * @property-read int|null $seal_marks_count
 * @property-read UserSeal|null $seals
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserStrike> $strikes
 * @property-read int|null $strikes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Sub> $subscribedSubs
 * @property-read int|null $subscribed_subs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read int|null $votes_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User approved()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User federated()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User local()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User rejected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User telegram()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAcademicDegree($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatarImageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCanCreateSubs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCredentials($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailChangeRequestedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailChangeToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereExpertiseAreas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFederatedAccountCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFederatedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFederatedInstance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFederatedUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereHighestLevelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInstitution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitationLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsGuest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsVerifiedExpert($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereKarmaPoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePendingEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfessionalTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePublications($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTelegramId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTelegramPhotoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTelegramUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class User extends Authenticatable implements CanResetPasswordContract, MustVerifyEmail
{
    use CanResetPassword;

    use HasApiTokens;

    use HasFactory;

    use HasPushSubscriptions;

    use Notifiable;

    use SoftDeletes;

    protected $fillable = [
        'username',
        'email',
        'password',
        'status',
        'rejection_reason',
        'karma_points',
        'invitation_limit',
        'highest_level_id',
        'locale',
        'display_name',
        'bio',
        'avatar',
        'avatar_url',
        'professional_title',
        'institution',
        'academic_degree',
        'expertise_areas',
        'settings',
        'is_verified_expert',
        'is_guest',
        'is_deleted',
        'deleted_at',
        'deletion_number',
        'email_verified_at',
        'pending_email',
        'email_change_token',
        'email_change_requested_at',
        // Federated identity fields
        'federated_id',
        'federated_instance',
        'federated_username',
        'federated_account_created_at',
        // Telegram identity fields
        'telegram_id',
        'telegram_username',
        'telegram_photo_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_change_requested_at' => 'datetime',
        'password' => 'hashed',
        'karma_points' => 'integer',
        'is_verified_expert' => 'boolean',
        'is_guest' => 'boolean',
        'is_deleted' => 'boolean',
        'can_create_subs' => 'boolean',
        'deleted_at' => 'datetime',
        'settings' => 'array',
        'federated_account_created_at' => 'datetime',
    ];

    public function getAvatarAttribute()
    {
        // If user has migrated to new image system, return URL from images table
        if ($this->avatar_image_id && $this->avatarImage) {
            return $this->avatarImage->getUrl('medium');
        }

        // Fallback to old URL system for backward compatibility
        return $this->attributes['avatar_url'] ?? null;
    }

    public function setAvatarUrlAttribute($value): void
    {
        $this->attributes['avatar_url'] = $value;
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(static function ($user): void {
            if (empty($user->username)) {
                $baseUsername = Str::slug(explode('@', $user->email)[0]);
                $username = $baseUsername;
                $count = 1;

                while (self::where('username', $username)->exists()) {
                    $username = $baseUsername . $count;
                    $count++;
                }

                $user->username = $username;
            }
        });
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new EmailVerificationNotification());
    }

    public function currentLevel(): BelongsTo
    {
        return $this->belongsTo(KarmaLevel::class, 'highest_level_id');
    }

    public function highestLevel(): BelongsTo
    {
        return $this->belongsTo(KarmaLevel::class, 'highest_level_id');
    }

    public function karmaLevel(): BelongsTo
    {
        return $this->belongsTo(KarmaLevel::class, 'highest_level_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function calculateCurrentLevel()
    {
        return KarmaLevel::where('required_karma', '<=', $this->karma_points)
            ->orderBy('required_karma', 'desc')
            ->first();
    }

    public function updateKarma(int $points)
    {
        $previousLevelId = $this->highest_level_id;

        $this->karma_points += $points;
        if ($this->karma_points < 0) {
            $this->karma_points = 0;
        }

        $currentLevel = $this->calculateCurrentLevel();

        if ($currentLevel && ($this->highest_level_id === null || $currentLevel->id > $this->highest_level_id)) {
            $this->highest_level_id = $currentLevel->id;

            $this->save();

            // Only send notification if it's not the initial level (karma 0) and level changed
            if ($previousLevelId !== $currentLevel->id && $currentLevel->required_karma > 0) {
                event(new \App\Events\KarmaLevelUp($this, $currentLevel));
                $this->notify(new \App\Notifications\KarmaLevelUp($currentLevel));
            }

            return $this;
        }

        $this->save();

        return $this;
    }

    public function getBadge()
    {
        $currentLevel = $this->currentLevel()->first();

        return $currentLevel ? $currentLevel->badge : null;
    }

    public function streak(): HasOne
    {
        return $this->hasOne(UserStreak::class);
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class)
            ->withPivot('progress', 'unlocked_at')
            ->withTimestamps();
    }

    public function getKarmaMultiplierAttribute()
    {
        $streak = $this->streak()->first();
        $streakMultiplier = $streak ? $streak->karma_multiplier : 1.0;

        return $streakMultiplier;
    }

    public function karmaHistory(): HasMany
    {
        return $this->hasMany(KarmaHistory::class);
    }

    public function recordKarma(int $amount, string $source, $sourceId = null, $description = null)
    {
        return KarmaHistory::record($this, $amount, $source, $sourceId, $description);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole($role)
    {
        if (is_string($role)) {
            return $this->roles()->where('slug', $role)->exists();
        }

        return $role->intersect($this->roles()->get())->count() > 0;
    }

    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function savedLists(): HasMany
    {
        return $this->hasMany(SavedList::class);
    }

    public function getFavoritesListAttribute()
    {
        $list = $this->savedLists()->where('type', 'favorite')
            ->where('user_id', $this->id)
            ->first();

        if (! $list) {
            $list = $this->savedLists()->create([
                'name' => 'Favorites',
                'type' => 'favorite',
                'is_public' => false,
                'slug' => 'favorites',
            ]);
        }

        return $list;
    }

    public function getReadLaterListAttribute()
    {
        $list = $this->savedLists()->where('type', 'read_later')
            ->where('user_id', $this->id)
            ->first();

        if (! $list) {
            $list = $this->savedLists()->create([
                'name' => 'read-later',
                'type' => 'read_later',
                'is_public' => false,
                'slug' => 'read-later',
            ]);
        }

        return $list;
    }

    public function hasFavorite(int $postId): bool
    {
        return $this->favorites_list->posts()->where('post_id', $postId)->exists();
    }

    public function hasReadLater(int $postId): bool
    {
        return $this->read_later_list->posts()->where('post_id', $postId)->exists();
    }

    public function hasSavedInList(int $postId, int $listId): bool
    {
        return $this->savedLists()
            ->where('id', $listId)
            ->whereHas('posts', static function ($query) use ($postId): void {
                $query->where('post_id', $postId);
            })
            ->exists();
    }

    public function bans(): HasMany
    {
        return $this->hasMany(UserBan::class);
    }

    public function strikes(): HasMany
    {
        return $this->hasMany(UserStrike::class);
    }

    public function isBanned(): bool
    {
        return $this->bans()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function isModerator(): bool
    {
        return $this->hasRole('moderator') || $this->hasRole('admin');
    }

    /**
     * Check if user account is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if user account is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if user account is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Scope to filter only approved users.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to filter only pending users.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter only rejected users.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Invitations created by the user.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'created_by');
    }

    /**
     * Get user's invitation limit.
     * Uses custom limit if set, otherwise calculates based on karma.
     */
    public function getInvitationLimit(): int
    {
        if ($this->invitation_limit !== null) {
            return $this->invitation_limit;
        }

        if ($this->hasRole('admin') && config('invitations.admin_unlimited')) {
            return PHP_INT_MAX;
        }

        if ($this->hasRole('moderator')) {
            return config('invitations.moderator_limit', 50);
        }

        $karmaLimits = config('invitations.karma_limits', [0 => 5]);
        $karma = $this->karma_points ?? 0;
        $limit = config('invitations.default_limit', 5);

        foreach ($karmaLimits as $threshold => $karmaLimit) {
            if ($karma >= $threshold) {
                $limit = $karmaLimit;
            }
        }

        return $limit;
    }

    /**
     * Get number of invitations created by user.
     */
    public function getInvitationCount(): int
    {
        return $this->invitations()->count();
    }

    /**
     * Get number of remaining invitations.
     */
    public function getRemainingInvitations(): int
    {
        $limit = $this->getInvitationLimit();

        if ($limit === PHP_INT_MAX) {
            return PHP_INT_MAX;
        }

        return max(0, $limit - $this->getInvitationCount());
    }

    /**
     * Check if user can create an invitation.
     */
    public function canCreateInvitation(): array
    {
        if ($this->is_guest && ! config('invitations.allow_guest_invitations')) {
            return ['can' => false, 'reason' => 'Guest users cannot create invitations'];
        }

        if (config('invitations.require_verified_email') && ! $this->hasVerifiedEmail()) {
            return ['can' => false, 'reason' => 'Email verification required'];
        }

        $minAge = config('invitations.minimum_account_age_days', 0);
        if ($minAge > 0 && $this->created_at->diffInDays(now()) < $minAge) {
            return ['can' => false, 'reason' => "Account must be at least {$minAge} days old"];
        }

        $minKarma = config('invitations.minimum_karma', 0);
        if (($this->karma_points ?? 0) < $minKarma) {
            return ['can' => false, 'reason' => "Minimum {$minKarma} karma required"];
        }

        $remaining = $this->getRemainingInvitations();
        if ($remaining <= 0 && $remaining !== PHP_INT_MAX) {
            return ['can' => false, 'reason' => 'Invitation limit reached'];
        }

        return ['can' => true, 'reason' => null];
    }

    /**
     * Check if the user account has been deleted.
     */
    public function isDeleted(): bool
    {
        return $this->is_deleted === true;
    }

    /**
     * Get the display name for the user, or "Usuario eliminado #123" if deleted.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_deleted) {
            return 'Usuario eliminado #' . $this->deletion_number;
        }

        return $this->attributes['display_name'] ?? $this->username;
    }

    /**
     * Get the username for display purposes.
     * Returns "[deleted]" for deleted users (Reddit style).
     */
    public function getDisplayUsername(): string
    {
        if ($this->is_deleted || $this->trashed()) {
            return '[deleted]';
        }

        return $this->username;
    }

    /**
     * Get the avatar image.
     */
    public function avatarImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'avatar_image_id');
    }

    /**
     * Get the user preferences.
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * Subcommunities the user is subscribed to (active only).
     */
    public function subscribedSubs(): BelongsToMany
    {
        return $this->belongsToMany(Sub::class, 'sub_subscriptions')
            ->wherePivot('status', 'active')
            ->withTimestamps();
    }

    /**
     * User seals (points for marking content).
     */
    public function seals(): HasOne
    {
        return $this->hasOne(UserSeal::class);
    }

    /**
     * Seal marks applied by this user.
     */
    public function sealMarks(): HasMany
    {
        return $this->hasMany(SealMark::class);
    }

    /**
     * ActivityPub/Federation settings for this user.
     */
    public function activityPubSettings(): HasOne
    {
        return $this->hasOne(ActivityPubUserSettings::class);
    }

    /**
     * ActivityPub actor for this user (for multi-actor federation).
     */
    public function activityPubActor(): HasOne
    {
        return $this->hasOne(ActivityPubActor::class, 'entity_id')
            ->where('actor_type', 'user');
    }

    /**
     * Scope to filter out deleted users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Check if user is currently snoozed (temporary silence).
     */
    public function isSnoozed(): bool
    {
        $prefs = $this->preferences;
        if (! $prefs || $prefs->snoozed_until === null) {
            return false;
        }

        return $prefs->snoozed_until->isFuture();
    }

    /**
     * Check if current time is within user's quiet hours.
     */
    public function isWithinQuietHours(): bool
    {
        $prefs = $this->preferences;
        if (! $prefs || ! $prefs->quiet_hours_enabled) {
            return false;
        }

        if ($prefs->quiet_hours_start === null || $prefs->quiet_hours_end === null) {
            return false;
        }

        $timezone = $prefs->timezone ?? 'Europe/Madrid';
        $now = now($timezone);
        $start = $now->copy()->setTimeFromTimeString($prefs->quiet_hours_start);
        $end = $now->copy()->setTimeFromTimeString($prefs->quiet_hours_end);

        // Handle overnight quiet hours (e.g., 23:00 to 08:00)
        if ($start->gt($end)) {
            return $now->gte($start) || $now->lte($end);
        }

        return $now->between($start, $end);
    }

    /**
     * Check if user should receive instant push for a specific category.
     */
    public function shouldReceiveInstantPush(string $category): bool
    {
        // First check snooze and quiet hours
        if ($this->isSnoozed() || $this->isWithinQuietHours()) {
            return false;
        }

        $prefs = $this->preferences;
        if (! $prefs) {
            return false;
        }

        // Check if push is enabled globally
        $notificationPrefs = $prefs->notification_preferences ?? [];
        $pushEnabled = $notificationPrefs['push']['enabled'] ?? false;

        if (! $pushEnabled) {
            return false;
        }

        // Check specific category
        $instantPrefs = $notificationPrefs['push']['instant'] ?? [];

        return $instantPrefs[$category] ?? false;
    }

    /**
     * Get user's notification preferences with defaults.
     */
    public function getNotificationPreferences(): array
    {
        $prefs = $this->preferences;
        $defaults = [
            'push' => [
                'enabled' => false,
                'instant' => [
                    'comment_replies' => true,
                    'post_comments' => true,
                    'mentions' => true,
                    'agora_messages' => true,
                    'agora_replies' => true,
                    'agora_mentions' => true,
                    'achievements' => false,
                    'karma_events' => false,
                    'system' => true,
                ],
            ],
            'digest' => [
                'enabled' => false,
                'include_popular_posts' => true,
                'include_activity_summary' => true,
                'include_subscribed_subs' => true,
            ],
        ];

        if (! $prefs) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $prefs->notification_preferences ?? []);
    }

    /**
     * Check if user has a pending email change.
     */
    public function hasPendingEmailChange(): bool
    {
        return $this->pending_email !== null && $this->email_change_token !== null;
    }

    /**
     * Clear pending email change data.
     */
    public function clearPendingEmailChange(): void
    {
        $this->pending_email = null;
        $this->email_change_token = null;
        $this->email_change_requested_at = null;
        $this->save();
    }

    /**
     * Check if email change token is expired (24 hours).
     */
    public function isEmailChangeTokenExpired(): bool
    {
        if ($this->email_change_requested_at === null) {
            return true;
        }

        return $this->email_change_requested_at->addHours(24)->isPast();
    }

    /**
     * Check if user is a federated user (logged in via Mastodon/Fediverse).
     */
    public function isFederated(): bool
    {
        return $this->federated_id !== null;
    }

    /**
     * Get the full federated handle (e.g., "@user@mastodon.social").
     */
    public function getFederatedHandleAttribute(): ?string
    {
        if (! $this->isFederated()) {
            return null;
        }

        return '@' . $this->federated_username . '@' . $this->federated_instance;
    }

    /**
     * Scope to filter only federated users.
     */
    public function scopeFederated($query)
    {
        return $query->whereNotNull('federated_id');
    }

    /**
     * Scope to filter only local (non-federated) users.
     */
    public function scopeLocal($query)
    {
        return $query->whereNull('federated_id');
    }

    /**
     * Find a user by their federated identity.
     */
    public static function findByFederatedId(string $federatedId): ?self
    {
        return self::where('federated_id', $federatedId)->first();
    }

    /**
     * Check if user logged in via Telegram.
     */
    public function isTelegramUser(): bool
    {
        return $this->telegram_id !== null;
    }

    /**
     * Scope to filter only Telegram users.
     */
    public function scopeTelegram($query)
    {
        return $query->whereNotNull('telegram_id');
    }

    /**
     * Check if user is a social login user (Mastodon, Telegram, etc.).
     */
    public function isSocialLoginUser(): bool
    {
        return $this->isFederated() || $this->isTelegramUser();
    }
}
