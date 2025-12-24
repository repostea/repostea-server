<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\AgoraMessageResource;
use App\Models\AgoraMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AgoraMessageCreated implements ShouldBroadcast
{
    use Dispatchable;

    use InteractsWithSockets;

    use SerializesModels;

    public function __construct(
        public AgoraMessage $message,
        public ?int $parentAuthorId = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            // Public channel for all users viewing Agora
            new Channel('agora'),
        ];

        // If it's a reply, notify the original message author
        if ($this->parentAuthorId && $this->parentAuthorId !== $this->message->user_id) {
            $channels[] = new PrivateChannel('user.' . $this->parentAuthorId);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.created';
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
            'is_reply' => $this->message->parent_id !== null,
            'parent_id' => $this->message->parent_id,
            'parent_author_id' => $this->parentAuthorId,
            'author_id' => $this->message->user_id,
        ];
    }
}
