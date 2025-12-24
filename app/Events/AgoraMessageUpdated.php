<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\AgoraMessageResource;
use App\Models\AgoraMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AgoraMessageUpdated implements ShouldBroadcast
{
    use Dispatchable;

    use InteractsWithSockets;

    use SerializesModels;

    public function __construct(
        public AgoraMessage $message,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('agora'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => (new AgoraMessageResource($this->message))->resolve(),
        ];
    }
}
