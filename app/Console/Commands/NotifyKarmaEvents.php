<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\KarmaEvent;
use App\Models\User;
use App\Notifications\KarmaEventStarting;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class NotifyKarmaEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'karma:notify-events {--hours=1}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Notify users about upcoming karma events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $hours = (int) $this->option('hours');
            $this->info("Searching for karma events starting in the next {$hours} hours...");

            $startTime = now();
            $endTime = now()->addHours($hours);

            // Search for upcoming events
            $upcomingEvents = KarmaEvent::where('is_active', true)
                ->where('start_at', '>=', $startTime)
                ->where('start_at', '<=', $endTime)
                ->get();

            $this->info("Found {$upcomingEvents->count()} upcoming events.");

            if ($upcomingEvents->isEmpty()) {
                return 0;
            }

            // For each event, notify active users
            foreach ($upcomingEvents as $event) {
                $this->notifyUsersAboutEvent($event);
            }

            return 0;
        } catch (Exception $e) {
            $this->error("Error notifying karma events: {$e->getMessage()}");
            Log::error("Error notifying karma events: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Notify users about a karma event.
     */
    private function notifyUsersAboutEvent(KarmaEvent $event): void
    {
        $this->info("Notifying users about the event: {$event->type}");

        // Get active users (you can adjust this criteria)
        $activeUsers = User::whereNotNull('email_verified_at')
            ->whereHas('streak', static function ($query): void {
                $query->where('last_activity_date', '>=', now()->subDays(7));
            })
            ->limit(1000) // Limit to avoid overloading the system
            ->get();

        $this->info("Sending notifications to {$activeUsers->count()} active users.");

        // Here we assume you have a KarmaEventStarting notification
        // If it doesn't exist, you should create it
        Notification::send($activeUsers, new KarmaEventStarting($event));

        $this->info('Notifications sent successfully.');
    }
}
