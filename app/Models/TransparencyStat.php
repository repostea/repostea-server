<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $total_posts
 * @property int $total_users
 * @property int $total_comments
 * @property int $total_aggregated_sources
 * @property int $reports_total
 * @property int $reports_processed
 * @property int $reports_pending
 * @property int $avg_response_hours
 * @property int $content_removed
 * @property int $warnings_issued
 * @property int $users_suspended
 * @property int $appeals_total
 * @property array<array-key, mixed>|null $report_types
 * @property \Illuminate\Support\Carbon $calculated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereAppealsTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereAvgResponseHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereCalculatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereContentRemoved($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereReportTypes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereReportsPending($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereReportsProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereReportsTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereTotalAggregatedSources($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereTotalComments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereTotalPosts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereTotalUsers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereUsersSuspended($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransparencyStat whereWarningsIssued($value)
 *
 * @mixin \Eloquent
 */
final class TransparencyStat extends Model
{
    protected $fillable = [
        'total_posts',
        'total_users',
        'total_comments',
        'total_aggregated_sources',
        'reports_total',
        'reports_processed',
        'reports_pending',
        'avg_response_hours',
        'content_removed',
        'warnings_issued',
        'users_suspended',
        'appeals_total',
        'report_types',
        'calculated_at',
    ];

    protected $casts = [
        'report_types' => 'array',
        'calculated_at' => 'datetime',
    ];

    /**
     * Get the latest transparency stats.
     */
    public static function getLatest(): ?self
    {
        return self::orderBy('calculated_at', 'desc')->first();
    }
}
