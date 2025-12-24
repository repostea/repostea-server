<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class ClearRateLimits extends Command
{
    protected $signature = 'rate-limit:clear {--type=all : Type of rate limit to clear (all, throttle, views, reports)}';

    protected $description = 'Clear rate limiting data from cache';

    public function handle(): int
    {
        $type = $this->option('type');

        $this->info('Clearing rate limits...');

        switch ($type) {
            case 'throttle':
                $this->clearThrottleLimits();
                break;
            case 'views':
                $this->clearViewLimits();
                break;
            case 'reports':
                $this->clearReportLimits();
                break;
            case 'all':
            default:
                $this->clearThrottleLimits();
                $this->clearViewLimits();
                $this->clearReportLimits();
                break;
        }

        $this->info('✓ Rate limits cleared successfully!');

        return self::SUCCESS;
    }

    private function clearThrottleLimits(): void
    {
        // Laravel throttle uses keys with format: throttle:{sha1}
        $this->clearCacheByPattern('throttle:*');
        $this->line('  - Throttle limits cleared');
    }

    private function clearViewLimits(): void
    {
        // View rate limiter keys
        $this->clearCacheByPattern('view_requests_*');
        $this->clearCacheByPattern('no_referrer_*');
        $this->clearCacheByPattern('blacklist_ip_*');
        $this->line('  - View limits cleared');
    }

    private function clearReportLimits(): void
    {
        // Legal reports rate limiting keys
        // Laravel throttle uses the route/action name
        $this->clearCacheByPattern('*legal-reports*');
        $this->line('  - Report limits cleared');
    }

    private function clearCacheByPattern(string $pattern): void
    {
        // For drivers that support patterns (redis, memcached)
        try {
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $redis = Cache::getStore()->connection();
                $keys = $redis->keys($pattern);
                if (! empty($keys)) {
                    $redis->del($keys);
                }
            } else {
                // For other drivers, clear all cache
                // (not ideal but safe)
                Cache::flush();
            }
        } catch (Exception $e) {
            // If it fails, clear all cache as fallback
            Cache::flush();
        }
    }
}
