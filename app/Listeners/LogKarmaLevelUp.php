<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\KarmaLevelUp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class LogKarmaLevelUp implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(KarmaLevelUp $event): void
    {
        $user = $event->user;
        $level = $event->level;

        Log::info("User {$user->id} ({$user->username}) has reached level {$level->name}");

        $user->recordKarma(
            0,
            'level_up',
            $level->id,
            "Level reached: {$level->name}",
        );

        // Send notification to the user
        $user->notify(new \App\Notifications\KarmaLevelUp($level));
    }
}
