<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legal_report_id
 * @property int $user_id
 * @property string $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read LegalReport|null $legalReport
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNote whereLegalReportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNote whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalReportNote whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class LegalReportNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_report_id',
        'user_id',
        'note',
    ];

    /**
     * Get the legal report this note belongs to.
     */
    public function legalReport(): BelongsTo
    {
        return $this->belongsTo(LegalReport::class);
    }

    /**
     * Get the user who created this note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
