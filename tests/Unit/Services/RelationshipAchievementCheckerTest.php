<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Achievement;
use App\Models\Post;
use App\Models\PostRelationship;
use App\Models\User;
use App\Services\RelationshipAchievementChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RelationshipAchievementCheckerTest extends TestCase
{
    use RefreshDatabase;

    private RelationshipAchievementChecker $checker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = new RelationshipAchievementChecker();
        $this->user = User::factory()->create();

        // Create relationship achievements
        Achievement::firstOrCreate(
            ['slug' => 'relationalist'],
            [
                'name' => 'Relacionista',
                'description' => 'Crea 2 relaciones entre posts',
                'icon' => 'fas fa-code-branch',
                'type' => 'action',
                'requirements' => [
                    'type' => 'relationships',
                    'count' => 2,
                    'min_score' => 0,
                ],
                'karma_bonus' => 5,
            ],
        );

        Achievement::firstOrCreate(
            ['slug' => 'valuable-connections'],
            [
                'name' => 'Conexiones Valiosas',
                'description' => '10 relaciones con +10 votos cada una',
                'icon' => 'fas fa-heart',
                'type' => 'special',
                'requirements' => [
                    'type' => 'relationships',
                    'count' => 10,
                    'min_score' => 10,
                ],
                'karma_bonus' => 100,
            ],
        );
    }

    public function test_it_grants_relationalist_achievement_with_2_relationships(): void
    {
        // Create 2 relationships (score doesn't matter for this achievement)
        $this->createRelationshipForUser($this->user, score: 0);
        $this->createRelationshipForUser($this->user, score: 0);

        $granted = $this->checker->checkAchievements($this->user);

        $this->assertCount(1, $granted);
        $this->assertEquals('relationalist', $granted[0]->slug);

        // Verify in database
        $this->assertDatabaseHas('achievement_user', [
            'user_id' => $this->user->id,
            'achievement_id' => Achievement::where('slug', 'relationalist')->first()->id,
            'progress' => 100,
        ]);
    }

    public function test_it_grants_valuable_connections_achievement(): void
    {
        // Create 10 relationships with score >= 10
        for ($i = 0; $i < 10; $i++) {
            $this->createRelationshipForUser($this->user, score: 10);
        }

        $granted = $this->checker->checkAchievements($this->user);

        // Should grant both achievements
        $slugs = collect($granted)->pluck('slug')->toArray();
        $this->assertContains('relationalist', $slugs);
        $this->assertContains('valuable-connections', $slugs);
    }

    public function test_it_does_not_grant_achievement_if_score_too_low(): void
    {
        // Create 10 relationships but with score < 10
        for ($i = 0; $i < 10; $i++) {
            $this->createRelationshipForUser($this->user, score: 5);
        }

        $granted = $this->checker->checkAchievements($this->user);

        // Should only grant relationalist
        $this->assertCount(1, $granted);
        $this->assertEquals('relationalist', $granted[0]->slug);
    }

    public function test_it_does_not_grant_achievement_if_count_too_low(): void
    {
        // Create only 1 relationship
        $this->createRelationshipForUser($this->user, score: 10);

        $granted = $this->checker->checkAchievements($this->user);

        $this->assertCount(0, $granted);
    }

    public function test_it_updates_progress_when_not_qualified(): void
    {
        $achievement = Achievement::where('slug', 'relationalist')->first();

        // Create 1 relationship (need 2 for achievement)
        $this->createRelationshipForUser($this->user, score: 0);

        $granted = $this->checker->checkAchievements($this->user);

        $this->assertCount(0, $granted);

        // Check progress is 50% (1 out of 2)
        $this->assertDatabaseHas('achievement_user', [
            'user_id' => $this->user->id,
            'achievement_id' => $achievement->id,
            'progress' => 50,
            'unlocked_at' => null,
        ]);
    }

    public function test_it_does_not_grant_achievement_twice(): void
    {
        // Create 2 relationships and grant achievement
        $this->createRelationshipForUser($this->user, score: 0);
        $this->createRelationshipForUser($this->user, score: 0);

        $granted1 = $this->checker->checkAchievements($this->user);
        $this->assertCount(1, $granted1);

        // Create another relationship
        $this->createRelationshipForUser($this->user, score: 0);

        $granted2 = $this->checker->checkAchievements($this->user);
        $this->assertCount(0, $granted2); // Should not grant again
    }

    public function test_it_grants_achievement_with_karma_bonus(): void
    {
        // Create 2 relationships
        $this->createRelationshipForUser($this->user, score: 0);
        $this->createRelationshipForUser($this->user, score: 0);

        $granted = $this->checker->checkAchievements($this->user);

        // Verify achievement was granted
        $this->assertCount(1, $granted);
        $this->assertEquals(5, $granted[0]->karma_bonus); // Verify karma bonus value
    }

    public function test_it_checks_achievements_after_vote(): void
    {
        // Create 2 relationships
        $relationship1 = $this->createRelationshipForUser($this->user, score: 0);
        $relationship2 = $this->createRelationshipForUser($this->user, score: 0);

        // Check after vote on second relationship
        $granted = $this->checker->checkAfterVote($relationship2->id);

        $this->assertCount(1, $granted);
        $this->assertEquals('relationalist', $granted[0]->slug);
    }

    public function test_it_returns_empty_array_for_non_existent_relationship(): void
    {
        $granted = $this->checker->checkAfterVote(99999);

        $this->assertCount(0, $granted);
    }

    /**
     * Helper method to create a relationship for a user with a specific score.
     */
    private function createRelationshipForUser(User $user, int $score): PostRelationship
    {
        $sourcePost = Post::factory()->create();
        $targetPost = Post::factory()->create();

        $relationship = PostRelationship::create([
            'source_post_id' => $sourcePost->id,
            'target_post_id' => $targetPost->id,
            'relationship_type' => 'related',
            'relation_category' => 'own',
            'created_by' => $user->id,
            'upvotes_count' => max(0, $score),
            'downvotes_count' => 0,
            'score' => $score,
        ]);

        return $relationship;
    }
}
