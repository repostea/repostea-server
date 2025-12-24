<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string $ip_address
 * @property string|null $user_agent
 * @property int $attempts
 * @property int $max_attempts
 * @property string|null $endpoint
 * @property string|null $method
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog byAction(string $action)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog byIp(string $ip)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog byUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog recent(int $hours = 24)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereMaxAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RateLimitLog whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class RateLimitLog extends Model
{
    public const UPDATED_AT = null; // Only track creation time

    protected $fillable = [
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'attempts',
        'max_attempts',
        'endpoint',
        'method',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    /**
     * Get the user that triggered the rate limit.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get recent violations.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope to get violations by action.
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to get violations by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get violations by IP.
     */
    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Get suspicious users (multiple violations).
     */
    public static function getSuspiciousUsers(int $hours = 24, int $minViolations = 5)
    {
        return self::select('user_id')
            ->selectRaw('COUNT(*) as violation_count')
            ->selectRaw('COUNT(DISTINCT action) as unique_actions')
            ->selectRaw('MIN(created_at) as first_violation')
            ->selectRaw('MAX(created_at) as last_violation')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('user_id')
            ->having('violation_count', '>=', $minViolations)
            ->with('user:id,username,email,karma_points')
            ->get();
    }

    /**
     * Get violations grouped by action.
     */
    public static function getViolationsByAction(int $hours = 24)
    {
        return self::select('action')
            ->selectRaw('COUNT(*) as total_violations')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_users')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_ips')
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('action')
            ->orderByDesc('total_violations')
            ->get();
    }

    /**
     * Get violations grouped by hour for charts.
     */
    public static function getViolationsOverTime(int $hours = 24)
    {
        return self::selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour")
            ->selectRaw('COUNT(*) as violations')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_users')
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    /**
     * Clean up old logs based on retention policy.
     */
    public static function cleanupOldLogs(): int
    {
        $retentionDays = config('rate_limits.monitoring.log_retention_days', 30);

        return self::where('created_at', '<', now()->subDays($retentionDays))
            ->delete();
    }
}
