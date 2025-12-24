<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event for broadcasting post stats updates (votes, comments, views).
 * This event is dispatched in batches by the BroadcastThrottleService.
 */
final class PostStatsUpdated implements ShouldBroadcast
{
    use Dispatchable;

    use InteractsWithSockets;

    /**
     * @param  string  $channel  The channel name (e.g., 'posts.frontpage', 'post.123')
     * @param  array<int, array{id: int, votes?: int, comments_count?: int, views?: int}>  $updates  Array of post updates
     */
    public function __construct(
        public string $channel,
        public array $updates,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel($this->channel),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'stats.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'updates' => $this->updates,
            'timestamp' => now()->timestamp,
        ];
    }
}
