<?php

declare(strict_types=1);

namespace Tests\Unit;

use const PHP_INT_MAX;

use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_karma_based_invitation_limit(): void
    {
        // Test different karma levels
        $testCases = [
            ['karma' => 0, 'expected' => 5],
            ['karma' => 50, 'expected' => 5],
            ['karma' => 100, 'expected' => 10],
            ['karma' => 500, 'expected' => 20],
            ['karma' => 1000, 'expected' => 30],
            ['karma' => 5000, 'expected' => 50],
            ['karma' => 10000, 'expected' => 100],
        ];

        foreach ($testCases as $case) {
            $user = User::factory()->create([
                'karma_points' => $case['karma'],
                'invitation_limit' => null, // No custom limit
            ]);

            $this->assertEquals(
                $case['expected'],
                $user->getInvitationLimit(),
                "User with {$case['karma']} karma should have {$case['expected']} invitations",
            );
        }
    }

    #[Test]
    public function it_returns_custom_invitation_limit_when_set(): void
    {
        $user = User::factory()->create([
            'karma_points' => 100,
            'invitation_limit' => 25,
        ]);

        // Should return custom limit, not karma-based (which would be 10)
        $this->assertEquals(25, $user->getInvitationLimit());
    }

    #[Test]
    public function it_returns_unlimited_for_admin_users(): void
    {
        $user = User::factory()->create([
            'karma_points' => 50,
        ]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Administrator role'],
        );
        $user->roles()->attach($adminRole);

        $this->assertEquals(PHP_INT_MAX, $user->getInvitationLimit());
    }

    #[Test]
    public function it_returns_moderator_limit_for_moderators(): void
    {
        $user = User::factory()->create([
            'karma_points' => 50,
        ]);

        $moderatorRole = Role::firstOrCreate(
            ['slug' => 'moderator'],
            ['name' => 'moderator', 'display_name' => 'Moderator', 'description' => 'Moderator role'],
        );
        $user->roles()->attach($moderatorRole);

        $this->assertEquals(50, $user->getInvitationLimit());
    }

    #[Test]
    public function admin_role_takes_precedence_over_moderator(): void
    {
        $user = User::factory()->create([
            'karma_points' => 50,
        ]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Administrator role'],
        );
        $moderatorRole = Role::firstOrCreate(
            ['slug' => 'moderator'],
            ['name' => 'moderator', 'display_name' => 'Moderator', 'description' => 'Moderator role'],
        );
        $user->roles()->attach([$adminRole->id, $moderatorRole->id]);

        $this->assertEquals(PHP_INT_MAX, $user->getInvitationLimit());
    }

    #[Test]
    public function custom_limit_takes_precedence_over_roles(): void
    {
        $user = User::factory()->create([
            'karma_points' => 50,
            'invitation_limit' => 15,
        ]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Administrator role'],
        );
        $user->roles()->attach($adminRole);

        // Custom limit should override admin unlimited
        $this->assertEquals(15, $user->getInvitationLimit());
    }

    #[Test]
    public function it_counts_user_invitations(): void
    {
        $user = User::factory()->create();

        // Initially zero
        $this->assertEquals(0, $user->getInvitationCount());

        // Create some invitations
        Invitation::factory()->count(3)->create([
            'created_by' => $user->id,
        ]);

        // Should count them
        $this->assertEquals(3, $user->getInvitationCount());

        // Create invitation for another user
        $otherUser = User::factory()->create();
        Invitation::factory()->create([
            'created_by' => $otherUser->id,
        ]);

        // Should still be 3
        $this->assertEquals(3, $user->getInvitationCount());
    }

    #[Test]
    public function it_calculates_remaining_invitations(): void
    {
        $user = User::factory()->create([
            'karma_points' => 100, // Limit: 10
        ]);

        // Initially should have all 10
        $this->assertEquals(10, $user->getRemainingInvitations());

        // Create 3 invitations
        Invitation::factory()->count(3)->create([
            'created_by' => $user->id,
        ]);

        // Should have 7 remaining
        $this->assertEquals(7, $user->getRemainingInvitations());

        // Create 7 more
        Invitation::factory()->count(7)->create([
            'created_by' => $user->id,
        ]);

        // Should have 0 remaining
        $this->assertEquals(0, $user->getRemainingInvitations());
    }

    #[Test]
    public function remaining_invitations_never_goes_negative(): void
    {
        $user = User::factory()->create([
            'karma_points' => 0, // Limit: 5
        ]);

        // Create more invitations than the limit
        Invitation::factory()->count(10)->create([
            'created_by' => $user->id,
        ]);

        // Should be 0, not negative
        $this->assertEquals(0, $user->getRemainingInvitations());
    }

    #[Test]
    public function admin_users_have_unlimited_remaining_invitations(): void
    {
        $user = User::factory()->create();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Administrator role'],
        );
        $user->roles()->attach($adminRole);

        // Even with invitations created
        Invitation::factory()->count(100)->create([
            'created_by' => $user->id,
        ]);

        $this->assertEquals(PHP_INT_MAX, $user->getRemainingInvitations());
    }

    #[Test]
    public function guest_users_cannot_create_invitations(): void
    {
        $user = User::factory()->create([
            'is_guest' => true,
            'email_verified_at' => now(),
        ]);

        $result = $user->canCreateInvitation();

        $this->assertFalse($result['can']);
        $this->assertEquals('Guest users cannot create invitations', $result['reason']);
    }

    #[Test]
    public function unverified_users_cannot_create_invitations(): void
    {
        $user = User::factory()->create([
            'is_guest' => false,
            'email_verified_at' => null,
        ]);

        $result = $user->canCreateInvitation();

        $this->assertFalse($result['can']);
        $this->assertEquals('Email verification required', $result['reason']);
    }

    #[Test]
    public function users_at_limit_cannot_create_invitations(): void
    {
        $user = User::factory()->create([
            'karma_points' => 0, // Limit: 5
            'email_verified_at' => now(),
        ]);

        // Create invitations up to limit
        Invitation::factory()->count(5)->create([
            'created_by' => $user->id,
        ]);

        $result = $user->canCreateInvitation();

        $this->assertFalse($result['can']);
        $this->assertEquals('Invitation limit reached', $result['reason']);
    }

    #[Test]
    public function users_below_limit_can_create_invitations(): void
    {
        $user = User::factory()->create([
            'karma_points' => 100, // Limit: 10
            'email_verified_at' => now(),
        ]);

        // Create some invitations but not at limit
        Invitation::factory()->count(5)->create([
            'created_by' => $user->id,
        ]);

        $result = $user->canCreateInvitation();

        $this->assertTrue($result['can']);
        $this->assertNull($result['reason']);
    }

    #[Test]
    public function admin_users_can_always_create_invitations(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Administrator role'],
        );
        $user->roles()->attach($adminRole);

        // Even with many invitations
        Invitation::factory()->count(1000)->create([
            'created_by' => $user->id,
        ]);

        $result = $user->canCreateInvitation();

        $this->assertTrue($result['can']);
        $this->assertNull($result['reason']);
    }

    #[Test]
    public function it_has_invitations_relationship(): void
    {
        $user = User::factory()->create();

        $invitation1 = Invitation::factory()->create([
            'created_by' => $user->id,
        ]);

        $invitation2 = Invitation::factory()->create([
            'created_by' => $user->id,
        ]);

        // Create invitation for another user
        $otherUser = User::factory()->create();
        Invitation::factory()->create([
            'created_by' => $otherUser->id,
        ]);

        $invitations = $user->invitations;

        $this->assertCount(2, $invitations);
        $this->assertTrue($invitations->contains($invitation1));
        $this->assertTrue($invitations->contains($invitation2));
    }

    #[Test]
    public function invitation_limit_of_zero_prevents_creation(): void
    {
        $user = User::factory()->create([
            'invitation_limit' => 0,
            'email_verified_at' => now(),
        ]);

        $result = $user->canCreateInvitation();

        $this->assertFalse($result['can']);
        $this->assertEquals('Invitation limit reached', $result['reason']);
    }

    #[Test]
    public function karma_points_null_defaults_to_zero(): void
    {
        $user = User::factory()->create([
            'karma_points' => 0,
        ]);

        // Set karma_points to null after creation to test the null handling
        $user->karma_points = null;

        // Should get default limit for 0 karma
        $this->assertEquals(5, $user->getInvitationLimit());
    }

    #[Test]
    public function negative_karma_gets_minimum_limit(): void
    {
        $user = User::factory()->create([
            'karma_points' => -100,
        ]);

        // Should get default limit (0 karma tier)
        $this->assertEquals(5, $user->getInvitationLimit());
    }

    #[Test]
    public function very_high_karma_gets_maximum_limit(): void
    {
        $user = User::factory()->create([
            'karma_points' => 999999,
        ]);

        // Should get highest limit (10000+ tier)
        $this->assertEquals(100, $user->getInvitationLimit());
    }

    #[Test]
    public function invitation_limit_boundaries_are_correct(): void
    {
        $boundaries = [
            99 => 5,    // Just below 100
            100 => 10,  // Exactly 100
            101 => 10,  // Just above 100
            499 => 10,  // Just below 500
            500 => 20,  // Exactly 500
            501 => 20,  // Just above 500
        ];

        foreach ($boundaries as $karma => $expectedLimit) {
            $user = User::factory()->create([
                'karma_points' => $karma,
            ]);

            $this->assertEquals(
                $expectedLimit,
                $user->getInvitationLimit(),
                "Karma {$karma} should have limit {$expectedLimit}",
            );
        }
    }
}
