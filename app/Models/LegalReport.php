<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $reference_number
 * @property string $type
 * @property string $content_url
 * @property string $reporter_name
 * @property string $reporter_email
 * @property string|null $reporter_organization
 * @property string $description
 * @property string|null $original_url
 * @property string|null $ownership_proof
 * @property bool $good_faith
 * @property bool $accurate_info
 * @property bool $authorized
 * @property string $status
 * @property string|null $admin_notes
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $ip_address
 * @property string $locale
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $notification_sent_at
 * @property int|null $notification_sent_by
 * @property string|null $notification_locale
 * @property string|null $notification_content
 * @property string|null $notification_status
 * @property string|null $notification_error
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LegalReportNote> $notes
 * @property-read int|null $notes_count
 * @property-read User|null $notificationSender
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LegalReportNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read User|null $reviewer
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport resolved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereAccurateInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereAdminNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereAuthorized($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereContentUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereGoodFaith($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereNotificationContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereNotificationError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereNotificationLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereNotificationSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereNotificationSentBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereNotificationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereOriginalUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereOwnershipProof($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereReporterEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereReporterName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereReporterOrganization($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereReviewedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereReviewedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReport whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class LegalReport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'reference_number',
        'type',
        'content_url',
        'reporter_name',
        'reporter_email',
        'reporter_organization',
        'description',
        'original_url',
        'ownership_proof',
        'good_faith',
        'accurate_info',
        'authorized',
        'status',
        'admin_notes',
        'user_response',
        'response_sent_at',
        'reviewed_by',
        'reviewed_at',
        'ip_address',
        'locale',
        'notification_sent_at',
        'notification_sent_by',
        'notification_locale',
        'notification_content',
        'notification_status',
        'notification_error',
    ];

    protected $casts = [
        'good_faith' => 'boolean',
        'accurate_info' => 'boolean',
        'authorized' => 'boolean',
        'reviewed_at' => 'datetime',
        'response_sent_at' => 'datetime',
        'notification_sent_at' => 'datetime',
    ];

    /**
     * Get the reviewer who handled this report.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the user who sent the notification email.
     */
    public function notificationSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notification_sent_by');
    }

    /**
     * Get all internal notes for this report.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LegalReportNote::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get all notification attempts for this report.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(LegalReportNotification::class)->orderBy('created_at', 'desc');
    }

    /**
     * Scope to get pending reports.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get resolved reports.
     */
    public function scopeResolved($query)
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    /**
     * Mark report as under review.
     */
    public function markAsReviewing(?int $reviewerId = null): void
    {
        $this->update([
            'status' => self::STATUS_UNDER_REVIEW,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Mark report as resolved.
     */
    public function markAsResolved(?string $adminNotes = null, ?int $reviewerId = null): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'admin_notes' => $adminNotes,
            'reviewed_by' => $reviewerId ?? $this->reviewed_by,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Mark report as rejected.
     */
    public function markAsRejected(?string $adminNotes = null, ?int $reviewerId = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'admin_notes' => $adminNotes,
            'reviewed_by' => $reviewerId ?? $this->reviewed_by,
            'reviewed_at' => now(),
        ]);
    }
}
