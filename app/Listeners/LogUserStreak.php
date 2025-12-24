<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserStreak;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class LogUserStreak implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct() {}

    public function handle(UserStreak $event): void
    {
        $user = $event->user;
        $streakDays = $event->streakDays;

        Log::info("User {$user->id} ({$user->username}) has reached a streak of {$streakDays} days");

        $this->checkStreakMilestones($user, $streakDays);
    }

    private function checkStreakMilestones($user, $streakDays): void
    {
        $karmaBonus = 0;
        $description = null;

        switch ($streakDays) {
            case 7:
                $karmaBonus = 50;
                $description = '7-day consecutive streak';
                break;
            case 30:
                $karmaBonus = 200;
                $description = '30-day consecutive streak';
                break;
            case 90:
                $karmaBonus = 500;
                $description = '90-day consecutive streak';
                break;
            case 180:
                $karmaBonus = 1000;
                $description = '180-day consecutive streak';
                break;
            case 365:
                $karmaBonus = 2000;
                $description = '365-day consecutive streak';
                break;
        }

        if ($karmaBonus > 0) {
            $user->updateKarma($karmaBonus);

            $user->recordKarma(
                $karmaBonus,
                'streak',
                $streakDays,
                $description,
            );
        }
    }
}
