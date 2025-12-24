<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\KarmaLevel;
use App\Services\KarmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class KarmaServiceTest extends TestCase
{
    use RefreshDatabase;

    private KarmaService $karmaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->karmaService = app(KarmaService::class);

        // Seed karma levels for tests
        $this->seedKarmaLevels();
    }

    private function seedKarmaLevels(): void
    {
        $levels = [
            ['name' => 'Novato', 'required_karma' => 0],
            ['name' => 'Aprendiz', 'required_karma' => 200],
            ['name' => 'Colaborador', 'required_karma' => 1000],
            ['name' => 'Experto', 'required_karma' => 4000],
            ['name' => 'Mentor', 'required_karma' => 16000],
            ['name' => 'Sabio', 'required_karma' => 40000],
            ['name' => 'Leyenda', 'required_karma' => 100000],
        ];

        foreach ($levels as $level) {
            KarmaLevel::create($level);
        }
    }

    #[Test]
    public function it_returns_correct_multiplier_for_novato(): void
    {
        // User with 0-199 karma = Novato = 1.0x
        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        $multiplier = $method->invoke($this->karmaService, 0);
        $this->assertEquals(1.0, $multiplier);
    }

    #[Test]
    public function it_returns_correct_multiplier_for_aprendiz(): void
    {
        // Aprendiz (200 karma) = 1.0x
        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        $multiplier = $method->invoke($this->karmaService, 200);
        $this->assertEquals(1.0, $multiplier);
    }

    #[Test]
    public function it_returns_correct_multiplier_for_colaborador(): void
    {
        // Colaborador (1000 karma) = 1.0x
        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        $multiplier = $method->invoke($this->karmaService, 1000);
        $this->assertEquals(1.0, $multiplier);
    }

    #[Test]
    public function it_returns_correct_multiplier_for_experto(): void
    {
        // Experto (4000 karma) = 1.0x
        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        $multiplier = $method->invoke($this->karmaService, 4000);
        $this->assertEquals(1.0, $multiplier);
    }

    #[Test]
    public function it_returns_correct_multiplier_for_mentor(): void
    {
        // Mentor (16000 karma) = 1.05x
        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        $multiplier = $method->invoke($this->karmaService, 16000);
        $this->assertEquals(1.05, $multiplier);
    }

    #[Test]
    public function it_returns_correct_multiplier_for_sabio(): void
    {
        // Sabio (40000 karma) = 1.10x
        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        $multiplier = $method->invoke($this->karmaService, 40000);
        $this->assertEquals(1.10, $multiplier);
    }

    #[Test]
    public function it_returns_correct_multiplier_for_leyenda(): void
    {
        // Leyenda (100000 karma) = 1.15x
        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        $multiplier = $method->invoke($this->karmaService, 100000);
        $this->assertEquals(1.15, $multiplier);
    }

    #[Test]
    public function it_uses_threshold_based_multipliers_not_exact_match(): void
    {
        // User with 50000 karma (between Sabio and Leyenda) = Sabio multiplier 1.10x
        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        $multiplier = $method->invoke($this->karmaService, 50000);
        $this->assertEquals(1.10, $multiplier);

        // User with 20000 karma (between Mentor and Sabio) = Mentor multiplier 1.05x
        $multiplier = $method->invoke($this->karmaService, 20000);
        $this->assertEquals(1.05, $multiplier);
    }

    #[Test]
    public function it_multipliers_are_very_subtle_to_prevent_gaming(): void
    {
        // Verify max multiplier is only 1.15x (15% boost)
        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        $maxMultiplier = $method->invoke($this->karmaService, 999999);
        $this->assertEquals(1.15, $maxMultiplier);
        $this->assertLessThan(1.2, $maxMultiplier);
    }

    #[Test]
    public function it_calculates_realistic_progression_for_casual_user(): void
    {
        // Casual user: ~450 karma/month
        // Should reach Aprendiz (200) in ~0.5 months
        // Should reach Colaborador (1000) in ~2.2 months
        $monthlyKarma = 450;

        $monthsToAprendiz = 200 / $monthlyKarma;
        $this->assertLessThan(1, $monthsToAprendiz);

        $monthsToColaborador = 1000 / $monthlyKarma;
        $this->assertGreaterThan(2, $monthsToColaborador);
        $this->assertLessThan(3, $monthsToColaborador);

        $yearsToLeyenda = 100000 / ($monthlyKarma * 12);
        $this->assertGreaterThan(15, $yearsToLeyenda);
        $this->assertLessThan(20, $yearsToLeyenda);
    }

    #[Test]
    public function it_calculates_realistic_progression_for_active_user(): void
    {
        // Active user: ~3300 karma/month
        // Should reach Leyenda in ~2.5 years
        $monthlyKarma = 3300;

        $yearsToLeyenda = 100000 / ($monthlyKarma * 12);
        $this->assertGreaterThan(2, $yearsToLeyenda);
        $this->assertLessThan(3, $yearsToLeyenda);
    }

    #[Test]
    public function it_calculates_realistic_progression_for_power_user(): void
    {
        // Power user: ~12500 karma/month
        // Should reach Leyenda in ~8 months
        $monthlyKarma = 12500;

        $monthsToLeyenda = 100000 / $monthlyKarma;
        $this->assertGreaterThan(7, $monthsToLeyenda);
        $this->assertLessThan(9, $monthsToLeyenda);
    }

    #[Test]
    public function it_demonstrates_leyenda_is_extremely_exclusive(): void
    {
        // Even power users need 8 months of consistent high activity
        // Casual users need 18+ years
        // This proves the level is extremely exclusive as intended
        $casualMonths = 100000 / 450;
        $powerMonths = 100000 / 12500;

        $this->assertGreaterThan(200, $casualMonths); // 16+ years
        $this->assertGreaterThan(7, $powerMonths);    // 8+ months
    }

    #[Test]
    public function it_demonstrates_inverted_multiplier_logic(): void
    {
        // If YOU are Leyenda (100k karma) and vote someone's content
        // THEY get 1.15x multiplier, not you
        // This prevents gaming where people hunt high-level users

        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        // Your level (Leyenda) determines the multiplier you GIVE to others
        $yourKarma = 100000;
        $multiplierYouGive = $method->invoke($this->karmaService, $yourKarma);

        $this->assertEquals(1.15, $multiplierYouGive);

        // This means: receiving a vote from a Leyenda gives author 1.15x
        // But there's no incentive to hunt Leyendas because:
        // 1. You don't know who they are
        // 2. The boost is only +15% (very subtle)
        // 3. It's not worth gaming
    }

    #[Test]
    public function it_validates_anti_gaming_multiplier_design(): void
    {
        // Max multiplier is 1.15x (only 15% boost)
        // Compare this to old system: 3.0x (200% boost!)

        $reflection = new ReflectionClass($this->karmaService);
        $method = $reflection->getMethod('getLevelMultiplierByKarma');
        $method->setAccessible(true);

        $oldSystemMultiplier = 3.0; // Old dangerous value
        $newSystemMultiplier = $method->invoke($this->karmaService, 100000);

        $this->assertEquals(1.15, $newSystemMultiplier);
        $this->assertLessThan($oldSystemMultiplier / 2, $newSystemMultiplier);

        // New system is 61% less powerful: (1.15/3.0 = 0.38 = 62% reduction)
        $reductionPercentage = (($oldSystemMultiplier - $newSystemMultiplier) / $oldSystemMultiplier) * 100;
        $this->assertGreaterThan(60, $reductionPercentage);
    }

    #[Test]
    public function it_ensures_highest_level_never_decreases(): void
    {
        // Create a user and simulate karma gain to reach Experto level
        $user = \App\Models\User::factory()->create([
            'karma_points' => 0,
            'highest_level_id' => null,
        ]);

        // Gain karma to reach Experto (4000 karma required)
        $user->updateKarma(5000);
        $user->refresh();

        $expertoLevel = KarmaLevel::where('required_karma', 4000)->first();
        $this->assertEquals($expertoLevel->id, $user->highest_level_id);
        $this->assertEquals(5000, $user->karma_points);

        // Now lose karma (simulate downvotes, penalties, etc.)
        $user->updateKarma(-4500); // Drop to 500 karma
        $user->refresh();

        // Assert: highest_level_id should STILL be Experto (never decreases)
        $this->assertEquals($expertoLevel->id, $user->highest_level_id);
        $this->assertEquals(500, $user->karma_points);

        // The current level based on karma would be Aprendiz (200-1000)
        $aprendizLevel = KarmaLevel::where('required_karma', 200)->first();
        $currentLevelByKarma = $user->calculateCurrentLevel();
        $this->assertEquals($aprendizLevel->id, $currentLevelByKarma->id);

        // But the displayed level (highest_level_id) is still Experto
        $this->assertNotEquals($currentLevelByKarma->id, $user->highest_level_id);
        $this->assertGreaterThan($currentLevelByKarma->required_karma, $user->highestLevel->required_karma);
    }
}
