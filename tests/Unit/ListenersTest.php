<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Events\KarmaLevelUp;
use App\Listeners\LogKarmaLevelUp;
use App\Models\KarmaLevel;
use App\Models\User;
use App\Notifications\KarmaLevelUp as KarmaLevelUpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ListenersTest extends TestCase
{
    use RefreshDatabase;

    // Tests for future implementation

    #[Test]
    public function notify_user_of_karma_level_up_sends_notification(): void
    {
        // This test uses LogKarmaLevelUp directly
        Notification::fake();

        $user = User::factory()->create();
        $level = KarmaLevel::factory()->create();

        $event = new KarmaLevelUp($user, $level);
        $listener = new LogKarmaLevelUp();

        $listener->handle($event);

        Notification::assertSentTo(
            $user,
            KarmaLevelUpNotification::class,
        );
    }

    #[Test]
    public function karma_level_up_notification_has_correct_content(): void
    {
        $user = User::factory()->create();
        $level = KarmaLevel::factory()->create([
            'name' => 'Expert',
            'description' => 'You are now an expert',
            'required_karma' => 500,
            'badge' => 'expert-badge.png',
        ]);

        $notification = new KarmaLevelUpNotification($level);

        $mailData = $notification->toMail($user)->toArray();
        $databaseData = $notification->toArray($user);

        $this->assertStringContainsString('Expert', $mailData['introLines'][0]);

        $this->assertEquals($level->id, $databaseData['level_id']);
        $this->assertEquals('Expert', $databaseData['level_name']);
        $this->assertEquals('expert-badge.png', $databaseData['badge']);
    }
}
