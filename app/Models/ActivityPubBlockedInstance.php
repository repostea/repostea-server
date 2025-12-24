<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $domain
 * @property string|null $reason
 * @property string $block_type
 * @property int|null $blocked_by
 * @property bool $is_active
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class ActivityPubBlockedInstance extends Model
{
    public const BLOCK_TYPE_FULL = 'full';

    public const BLOCK_TYPE_SILENCE = 'silence';

    protected $fillable = [
        'domain',
        'reason',
        'block_type',
        'blocked_by',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user who created this block.
     */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * Check if a domain is fully blocked (not just silenced).
     *
     * Full blocks prevent all federation; silenced instances can still communicate
     * but their content is hidden from public feeds.
     */
    public static function isBlocked(string $domain): bool
    {
        $domain = strtolower($domain);

        // Use cache for performance - only cache FULL blocks
        $blockedDomains = Cache::remember(
            'activitypub:blocked_domains',
            now()->addMinutes(5),
            fn () => self::where('is_active', true)
                ->where('block_type', self::BLOCK_TYPE_FULL)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->pluck('domain')
                ->map(fn ($d) => strtolower($d))
                ->toArray(),
        );

        return in_array($domain, $blockedDomains, true);
    }

    /**
     * Check if a domain is silenced (limited).
     */
    public static function isSilenced(string $domain): bool
    {
        $domain = strtolower($domain);

        return self::where('domain', $domain)
            ->where('is_active', true)
            ->where('block_type', self::BLOCK_TYPE_SILENCE)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Get the block status for a domain.
     *
     * @return array{blocked: bool, silenced: bool, reason: string|null}
     */
    public static function getStatus(string $domain): array
    {
        $domain = strtolower($domain);

        $block = self::where('domain', $domain)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($block === null) {
            return ['blocked' => false, 'silenced' => false, 'reason' => null];
        }

        return [
            'blocked' => $block->block_type === self::BLOCK_TYPE_FULL,
            'silenced' => $block->block_type === self::BLOCK_TYPE_SILENCE,
            'reason' => $block->reason,
        ];
    }

    /**
     * Block a domain.
     */
    public static function blockDomain(
        string $domain,
        ?string $reason = null,
        string $blockType = self::BLOCK_TYPE_FULL,
        ?int $blockedBy = null,
        ?\Carbon\Carbon $expiresAt = null,
    ): self {
        $domain = strtolower($domain);

        $instance = self::updateOrCreate(
            ['domain' => $domain],
            [
                'reason' => $reason,
                'block_type' => $blockType,
                'blocked_by' => $blockedBy,
                'is_active' => true,
                'expires_at' => $expiresAt,
            ],
        );

        self::clearCache();

        return $instance;
    }

    /**
     * Unblock a domain.
     */
    public static function unblockDomain(string $domain): bool
    {
        $domain = strtolower($domain);

        $deleted = self::where('domain', $domain)->delete();

        self::clearCache();

        return $deleted > 0;
    }

    /**
     * Deactivate a block instead of deleting.
     */
    public static function deactivateDomain(string $domain): bool
    {
        $domain = strtolower($domain);

        $updated = self::where('domain', $domain)
            ->update(['is_active' => false]);

        self::clearCache();

        return $updated > 0;
    }

    /**
     * Clear the blocked domains cache.
     */
    public static function clearCache(): void
    {
        Cache::forget('activitypub:blocked_domains');
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
     * Deactivate expired blocks.
     */
    public static function deactivateExpired(): int
    {
        $count = self::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['is_active' => false]);

        if ($count > 0) {
            self::clearCache();
        }

        return $count;
    }
}
