<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Events\AchievementUnlocked;
use App\Events\KarmaLevelUp;
use App\Events\UserStreak;
use App\Models\Achievement;
use App\Models\KarmaLevel;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EventsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_streak_event_has_correct_structure(): void
    {
        $user = User::factory()->create();
        $streakDays = 7;

        $event = new UserStreak($user, $streakDays);

        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
        $this->assertEquals('private-user.' . $user->id, $event->broadcastOn()->name);
        $this->assertEquals('user.streak', $event->broadcastAs());

        $data = $event->broadcastWith();
        $this->assertEquals($streakDays, $data['streak_days']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    #[Test]
    public function karma_level_up_event_has_correct_structure(): void
    {
        $user = User::factory()->create(['karma_points' => 100]);
        $level = KarmaLevel::factory()->create([
            'name' => 'Test Level',
            'required_karma' => 100,
            'badge' => 'test-badge.png',
            'description' => 'Test description',
        ]);

        $event = new KarmaLevelUp($user, $level);

        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
        $this->assertEquals('private-user.' . $user->id, $event->broadcastOn()->name);
        $this->assertEquals('karma.level.up', $event->broadcastAs());

        $data = $event->broadcastWith();
        $this->assertEquals($level->id, $data['level']['id']);
        $this->assertEquals('Test Level', $data['level']['name']);
        $this->assertEquals('test-badge.png', $data['level']['badge']);
        $this->assertEquals('Test description', $data['level']['description']);
        $this->assertEquals(100, $data['level']['required_karma']);
        $this->assertEquals(100, $data['karma_points']);
    }

    #[Test]
    public function achievement_unlocked_event_has_correct_structure(): void
    {
        $user = User::factory()->create();
        $achievement = Achievement::factory()->create([
            'name' => 'Test Achievement',
            'description' => 'Test achievement description',
            'icon' => 'test-icon.png',
            'karma_bonus' => 50,
        ]);

        $event = new AchievementUnlocked($user, $achievement);

        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
        $this->assertEquals('private-user.' . $user->id, $event->broadcastOn()->name);
        $this->assertEquals('achievement.unlocked', $event->broadcastAs());

        $data = $event->broadcastWith();
        $this->assertEquals($achievement->id, $data['achievement']['id']);
        $this->assertEquals('Test Achievement', $data['achievement']['name']);
        $this->assertEquals('Test achievement description', $data['achievement']['description']);
        $this->assertEquals('test-icon.png', $data['achievement']['icon']);
        $this->assertEquals(50, $data['achievement']['karma_bonus']);
    }
}
