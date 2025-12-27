<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $reported_by
 * @property int|null $reported_user_id
 * @property string $reportable_type
 * @property int $reportable_id
 * @property string $reason
 * @property string|null $description
 * @property string $status
 * @property int|null $reviewed_by
 * @property string|null $moderator_notes
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $reportedBy
 * @property-read User|null $reportedUser
 * @property-read User|null $reviewedBy
 * @property-read Model $reportable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ReportNote> $notes
 * @property-read int|null $notes_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereModeratorNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereReportableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereReportableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereReportedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereReportedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereReviewedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereReviewedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class Report extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'reported_by',
        'reported_user_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'description',
        'status',
        'reviewed_by',
        'moderator_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the reportable entity (Post, Comment, User).
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who made the report.
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Get the user being reported (if applicable).
     */
    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    /**
     * Get the moderator who reviewed the report.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if the report is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the report is resolved.
     */
    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Mark report as resolved.
     */
    public function resolve(int $moderatorId, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'reviewed_by' => $moderatorId,
            'moderator_notes' => $notes,
            'reviewed_at' => now(),
        ]);

        // Notify the reporter
        if ($this->reportedBy) {
            $this->reportedBy->notify(new \App\Notifications\ReportResolved($this));
        }
    }

    /**
     * Mark report as dismissed.
     */
    public function dismiss(int $moderatorId, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_DISMISSED,
            'reviewed_by' => $moderatorId,
            'moderator_notes' => $notes,
            'reviewed_at' => now(),
        ]);

        // Notify the reporter
        if ($this->reportedBy) {
            $this->reportedBy->notify(new \App\Notifications\ReportDismissed($this));
        }
    }

    /**
     * Reopen a resolved or dismissed report back to pending status.
     */
    public function reopen(int $moderatorId, ?string $notes = null): void
    {
        $existingNotes = $this->moderator_notes;
        $reopenNote = '[Reabierto por moderador el ' . now()->format('Y-m-d H:i:s') . ']';

        if ($notes) {
            $reopenNote .= ' ' . $notes;
        }

        $combinedNotes = $existingNotes
            ? $existingNotes . "\n\n" . $reopenNote
            : $reopenNote;

        $this->update([
            'status' => self::STATUS_PENDING,
            'reviewed_by' => $moderatorId,
            'moderator_notes' => $combinedNotes,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Check if the report can be reopened.
     */
    public function canBeReopened(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_DISMISSED]);
    }

    /**
     * Get all notes for this report.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ReportNote::class)->orderBy('created_at', 'asc');
    }
}
