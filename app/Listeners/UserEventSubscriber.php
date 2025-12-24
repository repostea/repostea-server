<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AchievementUnlocked;
use App\Events\KarmaLevelUp;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

final class UserEventSubscriber
{
    public function handleUserRegistration(Registered $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            Log::info("New user registered: {$user->username} (ID: {$user->id})");

            $noviceLevel = \App\Models\KarmaLevel::where('required_karma', 0)->first();
            if ($noviceLevel !== null) {
                $user->highest_level_id = $noviceLevel->id;
                $user->save();
            }

            \App\Models\UserStreak::create([
                'user_id' => $user->id,
                'current_streak' => 1,
                'longest_streak' => 1,
                'last_activity_date' => now(),
            ]);
        }
    }

    public function handleUserLogin(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            app(\App\Services\StreakService::class)->recordActivity($user);
        }
    }

    public function handleKarmaLevelUp(KarmaLevelUp $event): void
    {
        Log::channel('karma')->info(
            "LEVEL REACHED: User {$event->user->username} reached level {$event->level->name}",
        );
    }

    public function handleAchievementUnlocked(AchievementUnlocked $event): void
    {
        Log::channel('achievements')->info(
            "ACHIEVEMENT UNLOCKED: User {$event->user->username} unlocked {$event->achievement->name}",
        );

        // Send notification to user
        app(\App\Services\NotificationService::class)->notifyAchievementUnlocked(
            $event->user,
            $event->achievement,
        );
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Registered::class => 'handleUserRegistration',
            Login::class => 'handleUserLogin',
            KarmaLevelUp::class => 'handleKarmaLevelUp',
            AchievementUnlocked::class => 'handleAchievementUnlocked',
        ];
    }
}
