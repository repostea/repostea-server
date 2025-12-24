<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $ip_address
 * @property string $type
 * @property string|null $ip_range_start
 * @property string|null $ip_range_end
 * @property string $block_type
 * @property string $reason
 * @property string|null $notes
 * @property int|null $blocked_by
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property bool $is_active
 * @property array<array-key, mixed>|null $metadata
 * @property int $hit_count
 * @property \Illuminate\Support\Carbon|null $last_hit_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $blockedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock byCountry(string $countryCode)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock permanent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock recent(int $hours = 24)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock temporary()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereBlockType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereBlockedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereHitCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereIpRangeEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereIpRangeStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereLastHitAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpBlock whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class IpBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'type',
        'ip_range_start',
        'ip_range_end',
        'block_type',
        'reason',
        'notes',
        'blocked_by',
        'expires_at',
        'is_active',
        'metadata',
        'hit_count',
        'last_hit_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_hit_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'hit_count' => 'integer',
    ];

    /**
     * Get the user who blocked this IP.
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * Check if this block is currently active.
     */
    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if this block has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Increment hit count for this block.
     */
    public function recordHit(): void
    {
        $this->increment('hit_count');
        $this->update(['last_hit_at' => now()]);
    }

    /**
     * Scope for active blocks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope for permanent blocks.
     */
    public function scopePermanent($query)
    {
        return $query->where('block_type', 'permanent');
    }

    /**
     * Scope for temporary blocks.
     */
    public function scopeTemporary($query)
    {
        return $query->where('block_type', 'temporary');
    }

    /**
     * Scope for expired blocks.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
            ->where('is_active', true);
    }

    /**
     * Check if an IP address is blocked.
     */
    public static function isIpBlocked(string $ip): bool
    {
        $cacheKey = 'ip_block_' . $ip;

        return Cache::tags(['security'])->remember($cacheKey, now()->addMinutes(5), function () use ($ip) {
            // Check exact IP match
            $exactMatch = self::active()
                ->where('type', 'single')
                ->where('ip_address', $ip)
                ->exists();

            if ($exactMatch) {
                return true;
            }

            // Check IP range matches
            $ranges = self::active()
                ->where('type', 'range')
                ->get();

            foreach ($ranges as $range) {
                if (self::ipInRange($ip, $range->ip_range_start, $range->ip_range_end)) {
                    return true;
                }
            }

            // Check pattern matches (wildcard)
            $patterns = self::active()
                ->where('type', 'pattern')
                ->get();

            foreach ($patterns as $pattern) {
                if (self::ipMatchesPattern($ip, $pattern->ip_address)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Get the block record for an IP.
     */
    public static function getBlockForIp(string $ip): ?self
    {
        // Check exact IP match
        $exactMatch = self::active()
            ->where('type', 'single')
            ->where('ip_address', $ip)
            ->first();

        if ($exactMatch) {
            return $exactMatch;
        }

        // Check IP range matches
        $ranges = self::active()
            ->where('type', 'range')
            ->get();

        foreach ($ranges as $range) {
            if (self::ipInRange($ip, $range->ip_range_start, $range->ip_range_end)) {
                return $range;
            }
        }

        // Check pattern matches
        $patterns = self::active()
            ->where('type', 'pattern')
            ->get();

        foreach ($patterns as $pattern) {
            if (self::ipMatchesPattern($ip, $pattern->ip_address)) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Check if an IP is within a range.
     */
    private static function ipInRange(string $ip, string $rangeStart, string $rangeEnd): bool
    {
        $ipLong = ip2long($ip);
        $startLong = ip2long($rangeStart);
        $endLong = ip2long($rangeEnd);

        if ($ipLong === false || $startLong === false || $endLong === false) {
            return false;
        }

        return $ipLong >= $startLong && $ipLong <= $endLong;
    }

    /**
     * Check if an IP matches a pattern (supports * wildcard).
     */
    private static function ipMatchesPattern(string $ip, string $pattern): bool
    {
        // Convert pattern to regex
        // 192.168.*.* becomes ^192\.168\..*\..*$
        $regex = '/^' . str_replace(
            ['.', '*'],
            ['\.', '[0-9]+'],
            $pattern,
        ) . '$/';

        return (bool) preg_match($regex, $ip);
    }

    /**
     * Clear cache for specific IP.
     */
    public static function clearIpCache(string $ip): void
    {
        Cache::forget('ip_block_' . $ip);
    }

    /**
     * Clear all IP block cache.
     */
    public static function clearAllCache(): void
    {
        // This would require storing all IPs in a set, or using cache tags
        // For now, we rely on the 5-minute TTL
    }

    /**
     * Deactivate expired blocks (scheduled command).
     */
    public static function deactivateExpired(): int
    {
        return self::expired()->update(['is_active' => false]);
    }

    /**
     * Get most blocked IPs statistics.
     */
    public static function getMostBlockedIps(int $limit = 10)
    {
        return self::active()
            ->where('type', 'single')
            ->orderByDesc('hit_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get blocks created in last X hours.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get blocks by country (from metadata).
     */
    public function scopeByCountry($query, string $countryCode)
    {
        return $query->whereJsonContains('metadata->country', $countryCode);
    }
}
