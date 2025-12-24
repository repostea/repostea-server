<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Achievement;
use App\Models\KarmaEvent;
use App\Models\KarmaLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModelsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_has_many_achievements(): void
    {
        $user = User::factory()->create();
        $achievement1 = Achievement::factory()->create();
        $achievement2 = Achievement::factory()->create();

        $user->achievements()->attach([$achievement1->id, $achievement2->id]);

        $this->assertTrue($user->achievements->contains($achievement1));
        $this->assertTrue($user->achievements->contains($achievement2));
        $this->assertCount(2, $user->achievements);
    }

    #[Test]
    public function user_belongs_to_karma_level(): void
    {
        $level = KarmaLevel::factory()->create(['required_karma' => 100]);
        $user = User::factory()->create([
            'karma_points' => 150,
            'highest_level_id' => $level->id,
        ]);

        $this->assertInstanceOf(KarmaLevel::class, $user->highestLevel);
        $this->assertEquals($level->id, $user->highestLevel->id);
    }

    #[Test]
    public function karma_level_belongs_to_many_users(): void
    {
        $level = KarmaLevel::factory()->create();
        $user1 = User::factory()->create(['highest_level_id' => $level->id]);
        $user2 = User::factory()->create(['highest_level_id' => $level->id]);

        $this->assertCount(2, $level->users);
        $this->assertTrue($level->users->contains($user1));
        $this->assertTrue($level->users->contains($user2));
    }

    #[Test]
    public function karma_events_active_scope_returns_current_active_events(): void
    {
        KarmaEvent::factory()->create([
            'start_at' => now()->subHours(2),
            'end_at' => now()->addHours(2),
            'is_active' => true,
        ]);

        KarmaEvent::factory()->create([
            'start_at' => now()->subHours(5),
            'end_at' => now()->subHours(1),
            'is_active' => true,
        ]);

        KarmaEvent::factory()->create([
            'start_at' => now()->addHours(1),
            'end_at' => now()->addHours(5),
            'is_active' => true,
        ]);

        KarmaEvent::factory()->create([
            'start_at' => now()->subHours(2),
            'end_at' => now()->addHours(2),
            'is_active' => false,
        ]);

        $activeEvents = KarmaEvent::active()->get();
        $this->assertCount(1, $activeEvents);
        $this->assertTrue($activeEvents->first()->start_at < now());
        $this->assertTrue($activeEvents->first()->end_at > now());
        $this->assertTrue($activeEvents->first()->is_active);
    }

    #[Test]
    public function karma_events_upcoming_scope_returns_future_active_events(): void
    {
        KarmaEvent::factory()->create([
            'start_at' => now()->addHours(1),
            'end_at' => now()->addHours(5),
            'is_active' => true,
        ]);

        KarmaEvent::factory()->create([
            'start_at' => now()->addHours(2),
            'end_at' => now()->addHours(6),
            'is_active' => true,
        ]);

        KarmaEvent::factory()->create([
            'start_at' => now()->subHours(1),
            'end_at' => now()->addHours(3),
            'is_active' => true,
        ]);

        KarmaEvent::factory()->create([
            'start_at' => now()->addHours(1),
            'end_at' => now()->addHours(5),
            'is_active' => false,
        ]);

        $upcomingEvents = KarmaEvent::upcoming()->get();
        $this->assertCount(2, $upcomingEvents);
        foreach ($upcomingEvents as $event) {
            $this->assertTrue($event->start_at > now());
            $this->assertTrue($event->is_active);
        }
    }

    #[Test]
    public function achievement_has_correct_karma_bonus(): void
    {
        $achievement = Achievement::factory()->create([
            'karma_bonus' => 50,
        ]);

        $this->assertEquals(50, $achievement->karma_bonus);
    }

    #[Test]
    public function achievement_returns_translated_name(): void
    {
        $achievement = Achievement::factory()->create([
            'name' => 'achievements.welcome_title',
            'description' => 'achievements.welcome_description',
        ]);

        // Get translated attributes
        $translatedName = $achievement->translated_name;
        $translatedDescription = $achievement->translated_description;

        // Assert translations are returned (not the key)
        $this->assertNotEquals('achievements.welcome_title', $translatedName);
        $this->assertNotEquals('achievements.welcome_description', $translatedDescription);
        $this->assertIsString($translatedName);
        $this->assertIsString($translatedDescription);
    }

    #[Test]
    public function achievement_includes_translated_attributes_in_json(): void
    {
        $achievement = Achievement::factory()->create([
            'name' => 'achievements.first_post_title',
            'description' => 'achievements.first_post_description',
        ]);

        // Convert to array
        $achievementArray = $achievement->toArray();

        // Assert translated attributes are in array
        $this->assertArrayHasKey('translated_name', $achievementArray);
        $this->assertArrayHasKey('translated_description', $achievementArray);
        $this->assertIsString($achievementArray['translated_name']);
        $this->assertIsString($achievementArray['translated_description']);
    }
}
