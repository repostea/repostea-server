<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\KarmaLevel;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class KarmaLevelUp implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    use InteractsWithSockets;

    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
        public KarmaLevel $level,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->user->id);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs()
    {
        return 'karma.level.up';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'level' => [
                'id' => $this->level->id,
                'name' => $this->level->name,
                'badge' => $this->level->badge,
                'description' => $this->level->description,
                'required_karma' => $this->level->required_karma,
            ],
            'karma_points' => $this->user->karma_points,
        ];
    }
}
