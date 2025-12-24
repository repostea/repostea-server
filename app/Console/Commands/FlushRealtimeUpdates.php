<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RealtimeBroadcastService;
use Illuminate\Console\Command;

/**
 * Command to flush pending realtime updates.
 * Should be run frequently (every second) via scheduler.
 * The service handles dynamic throttling based on connected users.
 */
final class FlushRealtimeUpdates extends Command
{
    protected $signature = 'realtime:flush
                            {--force : Force flush regardless of throttle interval}';

    protected $description = 'Flush pending realtime post stats updates to connected clients';

    public function __construct(
        private readonly RealtimeBroadcastService $realtimeService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = $this->option('force');

        if (! $force && ! $this->realtimeService->shouldFlush()) {
            // Not time to flush yet based on dynamic throttle
            return self::SUCCESS;
        }

        $this->realtimeService->flushPendingUpdates();

        if ($this->output->isVerbose()) {
            $this->info(sprintf(
                'Flushed realtime updates (interval: %ds, connections: %d)',
                $this->realtimeService->getThrottleInterval(),
                $this->realtimeService->getConnectionsCount(),
            ));
        }

        return self::SUCCESS;
    }
}
