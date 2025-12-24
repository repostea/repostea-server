<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Achievement;
use App\Models\KarmaEvent;
use App\Models\KarmaLevel;
use App\Models\User;
use App\Notifications\AchievementUnlocked as AchievementUnlockedNotification;
use App\Notifications\KarmaEventStarting;
use DB;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends notifications for karma events, achievements, and level-ups.
 */
final class NotificationService
{
    /**
     * Notify active users about an upcoming karma event.
     */
    public function notifyUpcomingKarmaEvent(KarmaEvent $event, int $userLimit = 1000): int
    {
        try {
            $activeUsers = User::whereNotNull('email_verified_at')
                ->whereExists(static function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('user_streaks')
                        ->whereRaw('user_streaks.user_id = users.id')
                        ->where('last_activity_date', '>=', now()->subDays(7));
                })
                ->limit($userLimit)
                ->get();

            Notification::send($activeUsers, new KarmaEventStarting($event));

            return $activeUsers->count();
        } catch (Exception $e) {
            Log::error('Error notifying users about karma event', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function notifyAchievementUnlocked(User $user, Achievement $achievement): bool
    {
        try {
            $user->notify(new AchievementUnlockedNotification($achievement));

            return true;
        } catch (Exception $e) {
            Log::error('Error notifying user about achievement', [
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function notifyKarmaLevelUp(User $user, KarmaLevel $level): bool
    {
        try {
            Log::info("User {$user->id} reached karma level: {$level->name}");

            return true;
        } catch (Exception $e) {
            Log::error('Error notifying user about karma level', [
                'user_id' => $user->id,
                'level_id' => $level->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
