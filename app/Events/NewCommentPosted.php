<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event for broadcasting when a new comment is posted on a post.
 * Users viewing the post will see the new comment in real-time.
 */
final class NewCommentPosted implements ShouldBroadcast
{
    use Dispatchable;

    use InteractsWithSockets;

    use SerializesModels;

    public function __construct(
        public Comment $comment,
        public int $postId,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('post.' . $this->postId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'comment.created';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->comment->load(['user' => fn ($query) => $query->withTrashed()]);

        return [
            'comment' => (new CommentResource($this->comment))->resolve(),
            'post_id' => $this->postId,
            'parent_id' => $this->comment->parent_id,
            'timestamp' => now()->timestamp,
        ];
    }
}
