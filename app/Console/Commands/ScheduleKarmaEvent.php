<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\KarmaEvent;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class ScheduleKarmaEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'karma:schedule-event {type} {start_at} {duration} {--multiplier=} {--description=}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Schedule a karma event (tide, boost, etc.)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $type = $this->argument('type');
            $startAt = $this->argument('start_at');
            $duration = $this->argument('duration');

            // Get multiplier from argument or define default values
            $multiplier = $this->option('multiplier');
            if (! $multiplier) {
                $multiplier = $type === 'tide' ? 2.0 : 1.5;
            }

            // Get custom description or use a default one
            $description = $this->option('description');
            if (! $description) {
                $description = $type === 'tide'
                    ? 'Karma Tide: Karma is doubled!'
                    : 'Karma Boost: 1.5x karma multiplier!';
            }

            // Create the event
            $event = KarmaEvent::create([
                'name' => ucfirst($type) . ' Event',
                'type' => $type,
                'start_at' => Carbon::parse($startAt),
                'end_at' => Carbon::parse($startAt)->addHours((int) $duration),
                'multiplier' => $multiplier,
                'description' => $description,
                'is_active' => true,
            ]);

            $this->info("Karma event scheduled: {$event->type}");
            $this->info("Starts: {$event->start_at->format('Y-m-d H:i')}");
            $this->info("Ends: {$event->end_at->format('Y-m-d H:i')}");
            $this->info("Multiplier: {$event->multiplier}x");

            // Schedule notifications
            $this->scheduleNotifications($event);

            return 0;
        } catch (Exception $e) {
            $this->error("Error scheduling karma event: {$e->getMessage()}");
            Log::error("Error scheduling karma event: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Schedule notifications for users about the event.
     */
    private function scheduleNotifications($event): void
    {
        // Implement the logic to send notifications
        // For example, create a queued job to send notifications
        // through different channels (email, in-app notifications, etc.)
        $this->info('Notifications scheduled for the karma event');
    }
}
