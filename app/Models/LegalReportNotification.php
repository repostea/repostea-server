<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legal_report_id
 * @property int $sent_by
 * @property string $locale
 * @property string $content
 * @property string $status
 * @property string|null $error_message
 * @property string $recipient_email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read LegalReport $legalReport
 * @property-read User $sender
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification whereLegalReportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification whereRecipientEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification whereSentBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNotification whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class LegalReportNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_report_id',
        'sent_by',
        'locale',
        'content',
        'status',
        'error_message',
        'recipient_email',
    ];

    /**
     * Get the legal report this notification belongs to.
     */
    public function legalReport(): BelongsTo
    {
        return $this->belongsTo(LegalReport::class);
    }

    /**
     * Get the user who sent this notification.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
