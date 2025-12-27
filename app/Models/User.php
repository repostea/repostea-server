<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasBans;
use App\Models\Concerns\HasFederation;
use App\Models\Concerns\HasInvitations;
use App\Models\Concerns\HasKarma;
use App\Models\Concerns\HasNotificationPreferences;
use App\Models\Concerns\HasRoles;
use App\Models\Concerns\HasSavedLists;
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
    // Laravel framework traits
    use CanResetPassword;

    use HasApiTokens;

    // Application-specific traits
    use HasBans;

    use HasFactory;

    use HasFederation;

    use HasInvitations;

    use HasKarma;

    use HasNotificationPreferences;

    use HasPushSubscriptions;

    use HasRoles;

    use HasSavedLists;

    use Notifiable;

    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

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
        'two_factor_secret',
        'two_factor_recovery_codes',
        'email_change_token',
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

    public function getAvatarAttribute(): ?string
    {
        // If user has migrated to new image system, return URL from images table
        if ($this->avatar_image_id && $this->avatarImage) {
            return $this->avatarImage->getUrl();
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

    // =========================================================================
    // Relationships
    // =========================================================================

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

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class)
            ->withPivot('progress', 'unlocked_at')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Get the avatar image.
     */
    public function avatarImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'avatar_image_id');
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

    // =========================================================================
    // Status Methods
    // =========================================================================

    /**
     * Check if user account is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if user account is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if user account is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the user account has been deleted.
     */
    public function isDeleted(): bool
    {
        return $this->is_deleted === true;
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope to filter only approved users.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to filter only pending users.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter only rejected users.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to filter out deleted users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    // =========================================================================
    // Display & Presentation
    // =========================================================================

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

    // =========================================================================
    // Email Change
    // =========================================================================

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
}
