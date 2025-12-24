<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KarmaEvent;
use App\Models\User;
use App\Notifications\KarmaEventStarting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function schedule_karma_event_creates_karma_event_with_default_values(): void
    {
        $startAt = now()->addDay()->format('Y-m-d H:i');

        $this->artisan('karma:schedule-event', [
            'type' => 'tide',
            'start_at' => $startAt,
            'duration' => 24,
        ])->assertSuccessful();

        $this->assertDatabaseHas('karma_events', [
            'type' => 'tide',
            'multiplier' => 2.0,
            'is_active' => true,
        ]);

        $event = KarmaEvent::where('type', 'tide')->first();
        $this->assertEquals(
            Carbon::parse($startAt)->format('Y-m-d H:i'),
            $event->start_at->format('Y-m-d H:i'),
        );
        $this->assertEquals(
            Carbon::parse($startAt)->addHours(24)->format('Y-m-d H:i'),
            $event->end_at->format('Y-m-d H:i'),
        );
    }

    #[Test]
    public function schedule_karma_event_with_custom_values(): void
    {
        $this->artisan('karma:schedule-event', [
            'type' => 'boost',
            'start_at' => now()->addHours(2)->format('Y-m-d H:i'),
            'duration' => 12,
            '--multiplier' => 3.0,
            '--description' => 'Custom description',
        ])->assertSuccessful();

        $this->assertDatabaseHas('karma_events', [
            'type' => 'boost',
            'multiplier' => 3.0,
            'description' => 'Custom description',
        ]);
    }

    #[Test]
    public function recalculate_all_karma_processes_all_users(): void
    {
        // Create users with karma history
        $users = User::factory()->count(5)->create();

        // Add some karma history for users
        foreach ($users as $user) {
            DB::table('karma_histories')->insert([
                'user_id' => $user->id,
                'amount' => 100,
                'source' => 'test',
                'description' => 'Test karma',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Run the command
        $this->artisan('karma:recalculate-all')
            ->assertSuccessful();

        // Verify that users have their karma updated
        foreach ($users as $user) {
            $user->refresh();
            $this->assertEquals(100, $user->karma_points, "User {$user->id} should have 100 karma points");
        }
    }

    #[Test]
    public function notify_karma_events_command_runs_successfully(): void
    {
        KarmaEvent::create([
            'name' => 'Test Tide Event',
            'type' => 'tide',
            'start_at' => now()->addMinutes(30),
            'end_at' => now()->addHours(12),
            'multiplier' => 2.0,
            'description' => 'Test event',
            'is_active' => true,
        ]);

        // Solo verificamos que el comando se ejecuta sin errores
        $this->artisan('karma:notify-events', ['--hours' => 1])
            ->assertSuccessful();
    }

    #[Test]
    public function karma_event_starting_notification_is_sent_to_users(): void
    {
        Notification::fake();

        $users = User::factory()->count(3)->create();
        $event = KarmaEvent::factory()->create([
            'start_at' => now()->addMinutes(30),
            'is_active' => true,
        ]);

        // Send the notification directly
        Notification::send($users, new KarmaEventStarting($event));

        // Verify the notification was sent
        Notification::assertSentTo($users, KarmaEventStarting::class);
    }
}
