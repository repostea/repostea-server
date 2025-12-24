<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AchievementUnlocked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class LogAchievementUnlocked implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(AchievementUnlocked $event): void
    {
        $user = $event->user;
        $achievement = $event->achievement;

        Log::info("Usuario {$user->id} ({$user->username}) ha desbloqueado el logro: {$achievement->name}");

        $user->recordKarma(
            $achievement->karma_bonus,
            'achievement',
            $achievement->id,
            "Logro desbloqueado: {$achievement->name}",
        );
    }
}
