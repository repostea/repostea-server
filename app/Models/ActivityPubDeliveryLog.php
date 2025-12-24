<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $actor_id
 * @property string $inbox_url
 * @property string $instance
 * @property string $activity_type
 * @property string $status
 * @property int|null $http_status
 * @property string|null $error_message
 * @property int $attempt_count
 * @property \Carbon\Carbon|null $last_attempt_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read int|null $total
 * @property-read int|null $success
 * @property-read int|null $failed
 * @property-read int|null $total_attempts
 * @property-read int|null $failures
 * @property-read int|null $successes
 * @property-read string|null $last_attempt
 */
final class ActivityPubDeliveryLog extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'actor_id',
        'inbox_url',
        'instance',
        'activity_type',
        'status',
        'http_status',
        'error_message',
        'attempt_count',
        'last_attempt_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'http_status' => 'integer',
        'last_attempt_at' => 'datetime',
    ];

    /**
     * Get the actor that sent this activity.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(ActivityPubActor::class);
    }

    /**
     * Log a successful delivery.
     */
    public static function logSuccess(
        int $actorId,
        string $inboxUrl,
        string $activityType,
        int $httpStatus = 202,
    ): self {
        return self::create([
            'actor_id' => $actorId,
            'inbox_url' => $inboxUrl,
            'instance' => self::extractInstance($inboxUrl),
            'activity_type' => $activityType,
            'status' => self::STATUS_SUCCESS,
            'http_status' => $httpStatus,
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Log a failed delivery.
     */
    public static function logFailure(
        int $actorId,
        string $inboxUrl,
        string $activityType,
        ?int $httpStatus = null,
        ?string $errorMessage = null,
        int $attemptCount = 1,
    ): self {
        return self::create([
            'actor_id' => $actorId,
            'inbox_url' => $inboxUrl,
            'instance' => self::extractInstance($inboxUrl),
            'activity_type' => $activityType,
            'status' => self::STATUS_FAILED,
            'http_status' => $httpStatus,
            'error_message' => $errorMessage,
            'attempt_count' => $attemptCount,
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Extract the instance domain from an inbox URL.
     */
    public static function extractInstance(string $inboxUrl): string
    {
        $parsed = parse_url($inboxUrl);

        return $parsed['host'] ?? 'unknown';
    }

    /**
     * Get delivery statistics.
     *
     * @return array{total: int, success: int, failed: int, success_rate: float}
     */
    public static function getStats(?int $hours = 24): array
    {
        $query = self::query();

        if ($hours !== null) {
            $query->where('created_at', '>=', now()->subHours($hours));
        }

        $stats = $query->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        ")->first();

        $total = (int) ($stats->total ?? 0);
        $success = (int) ($stats->success ?? 0);
        $failed = (int) ($stats->failed ?? 0);

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get failure statistics by instance.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getFailuresByInstance(?int $hours = 24, int $limit = 20)
    {
        $query = self::query()
            ->select('instance')
            ->selectRaw('COUNT(*) as total_attempts')
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failures")
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successes")
            ->selectRaw('MAX(created_at) as last_attempt')
            ->groupBy('instance')
            ->having('failures', '>', 0)
            ->orderByDesc('failures')
            ->limit($limit);

        if ($hours !== null) {
            $query->where('created_at', '>=', now()->subHours($hours));
        }

        return $query->get()->map(function ($row) {
            $total = (int) $row->total_attempts;
            $failures = (int) $row->failures;

            return [
                'instance' => $row->instance,
                'total_attempts' => $total,
                'failures' => $failures,
                'successes' => (int) $row->successes,
                'failure_rate' => $total > 0 ? round(($failures / $total) * 100, 2) : 0,
                'last_attempt' => $row->last_attempt,
                'is_blocked' => ActivityPubBlockedInstance::isBlocked($row->instance),
            ];
        });
    }

    /**
     * Get recent failures.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getRecentFailures(int $limit = 50)
    {
        return self::query()
            ->with('actor:id,username,actor_type')
            ->where('status', self::STATUS_FAILED)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'instance' => $log->instance,
                'inbox_url' => $log->inbox_url,
                'activity_type' => $log->activity_type,
                'http_status' => $log->http_status,
                'error_message' => $log->error_message,
                'attempt_count' => $log->attempt_count,
                'actor' => $log->actor ? [
                    'username' => $log->actor->username,
                    'type' => $log->actor->actor_type,
                ] : null,
                'created_at' => $log->created_at->toIso8601String(),
            ]);
    }

    /**
     * Clean up old delivery logs.
     */
    public static function cleanup(int $daysToKeep = 7): int
    {
        return self::where('created_at', '<', now()->subDays($daysToKeep))->delete();
    }

    /**
     * Scope for failed deliveries.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for successful deliveries.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }
}
