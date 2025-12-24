<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $report_id
 * @property int $user_id
 * @property string $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Report $report
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportNote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportNote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportNote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportNote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportNote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportNote whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportNote whereReportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportNote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportNote whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class ReportNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'user_id',
        'note',
    ];

    /**
     * Get the report this note belongs to.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Get the user who created this note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
