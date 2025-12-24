<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\AgoraMessageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property int|null $root_id
 * @property string $content
 * @property int $votes_count
 * @property int $replies_count
 * @property int $total_replies_count
 * @property bool $is_anonymous
 * @property string|null $language_code
 * @property int|null $expires_in_hours
 * @property string|null $expiry_mode
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read AgoraMessage|null $parent
 * @property-read AgoraMessage|null $root
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AgoraMessage> $replies
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AgoraVote> $votes
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AgoraMessage> $threadMessages
 * @property-read int|null $thread_messages_count
 *
 * @method static \Database\Factories\AgoraMessageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereEditedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereExpiresInHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereExpiryMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereIsAnonymous($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereLanguageCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereRepliesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereRootId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereTotalRepliesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage whereVotesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraMessage withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy(AgoraMessageObserver::class)]
final class AgoraMessage extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_id',
        'root_id',
        'content',
        'votes_count',
        'replies_count',
        'total_replies_count',
        'is_anonymous',
        'language_code',
        'edited_at',
        'expires_in_hours',
        'expiry_mode',
        'expires_at',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'votes_count' => 'integer',
        'replies_count' => 'integer',
        'total_replies_count' => 'integer',
        'root_id' => 'integer',
        'edited_at' => 'datetime',
        'expires_in_hours' => 'integer',
        'expires_at' => 'datetime',
    ];

    // Expiry duration options in hours
    public const EXPIRY_OPTIONS = [
        '1_hour' => 1,
        '1_day' => 24,
        '1_week' => 168,
        '1_month' => 720,
        '1_year' => 8760,
        '1_century' => 876000,
    ];

    protected $with = ['user'];

    /**
     * Get the user that created the message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent message (for replies).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the root message of the thread.
     */
    public function root(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_id');
    }

    /**
     * Get all messages in the same thread (including self).
     */
    public function threadMessages(): HasMany
    {
        return $this->hasMany(self::class, 'root_id', 'root_id')->orderBy('created_at', 'asc');
    }

    /**
     * Get all replies to this message.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    /**
     * Get all votes for this message.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(AgoraVote::class);
    }

    /**
     * Check if message is a top-level message (not a reply).
     */
    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Update replies count.
     */
    public function updateRepliesCount(): void
    {
        $this->replies_count = $this->replies()->count();
        $this->saveQuietly();
    }

    /**
     * Calculate total replies count recursively (all nested replies).
     */
    public function calculateTotalRepliesCount(): int
    {
        $total = 0;
        $replies = $this->replies()->get();

        foreach ($replies as $reply) {
            $total++; // Count the reply itself
            $total += $reply->calculateTotalRepliesCount(); // Add nested replies
        }

        return $total;
    }

    /**
     * Update total replies count for this message and all ancestors.
     */
    public function updateTotalRepliesCount(): void
    {
        // Update this message's total count
        $this->total_replies_count = $this->calculateTotalRepliesCount();
        $this->saveQuietly();

        // Update all ancestors
        if ($this->parent_id) {
            $this->parent?->updateTotalRepliesCount();
        }
    }

    /**
     * Decrement total replies count for all ancestors (when a reply is deleted).
     *
     * @param  int  $decrementBy  Number of replies being removed (including nested)
     */
    public function decrementAncestorsTotalRepliesCount(int $decrementBy = 1): void
    {
        if ($this->parent_id) {
            $parent = $this->parent;
            if ($parent) {
                $parent->total_replies_count = max(0, $parent->total_replies_count - $decrementBy);
                $parent->saveQuietly();
                $parent->decrementAncestorsTotalRepliesCount($decrementBy);
            }
        }
    }

    /**
     * Increment total replies count for all ancestors (when a reply is added).
     */
    public function incrementAncestorsTotalRepliesCount(): void
    {
        if ($this->parent_id) {
            $parent = $this->parent;
            if ($parent) {
                $parent->total_replies_count = ($parent->total_replies_count ?? 0) + 1;
                $parent->saveQuietly();
                $parent->incrementAncestorsTotalRepliesCount();
            }
        }
    }

    /**
     * Update votes count.
     */
    public function updateVotesCount(): void
    {
        $this->votes_count = $this->votes()->sum('value');
        $this->saveQuietly();
    }

    /**
     * Get vote type summary (count of each vote type).
     *
     * @return array<string, int>
     */
    public function getVoteTypeSummary(): array
    {
        $votes = $this->votes()
            ->whereNotNull('vote_type')
            ->selectRaw('vote_type, SUM(value) as total')
            ->groupBy('vote_type')
            ->pluck('total', 'vote_type')
            ->toArray();

        return $votes;
    }

    /**
     * Calculate and set expires_at based on expiry settings.
     */
    public function calculateExpiresAt(): void
    {
        if (! $this->expires_in_hours) {
            $this->expires_at = null;

            return;
        }

        $baseTime = $this->expiry_mode === 'from_first'
            ? $this->created_at
            : ($this->getLatestActivityTime() ?? $this->created_at);

        $this->expires_at = $baseTime->copy()->addHours($this->expires_in_hours);
    }

    /**
     * Get the latest activity time (last reply or creation).
     */
    public function getLatestActivityTime(): ?\Carbon\Carbon
    {
        $lastReply = $this->replies()->latest()->first();

        return $lastReply?->created_at ?? $this->created_at;
    }

    /**
     * Update expiry for thread when a new reply is added (only if mode is from_last).
     */
    public function refreshExpiryOnNewReply(): void
    {
        // Only update if this is a top-level message with from_last mode
        if ($this->parent_id !== null || $this->expiry_mode !== 'from_last') {
            return;
        }

        $this->calculateExpiresAt();
        $this->saveQuietly();
    }

    /**
     * Check if message is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Get time remaining until expiry.
     */
    public function getTimeUntilExpiry(): ?string
    {
        if (! $this->expires_at) {
            return null;
        }

        return $this->expires_at->diffForHumans();
    }
}
