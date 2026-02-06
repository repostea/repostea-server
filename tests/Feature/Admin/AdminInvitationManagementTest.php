<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminInvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Administrator role'],
        );
        $this->admin->roles()->attach($adminRole);

        // Create regular user
        $this->user = User::factory()->create([
            'karma_points' => 100,
            'invitation_limit' => null,
        ]);
    }

    #[Test]
    public function admin_can_update_user_invitation_limit(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$this->user->id}/invitation-limit", [
                'invitation_limit' => 50,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify limit was updated
        $this->user->refresh();
        $this->assertEquals(50, $this->user->invitation_limit);
    }

    #[Test]
    public function admin_can_reset_user_invitation_limit(): void
    {
        // Set custom limit first
        $this->user->invitation_limit = 50;
        $this->user->save();

        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$this->user->id}/invitation-limit/reset");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify limit was reset
        $this->user->refresh();
        $this->assertNull($this->user->invitation_limit);
    }

    #[Test]
    public function admin_can_set_unlimited_invitation_limit(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$this->user->id}/invitation-limit", [
                'invitation_limit' => 10000,
            ]);

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertEquals(10000, $this->user->invitation_limit);
    }

    #[Test]
    public function admin_can_set_zero_invitation_limit(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$this->user->id}/invitation-limit", [
                'invitation_limit' => 0,
            ]);

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertEquals(0, $this->user->invitation_limit);

        // Verify user cannot create invitations
        $check = $this->user->canCreateInvitation();
        $this->assertFalse($check['can']);
    }

    #[Test]
    public function non_admin_cannot_update_invitation_limit(): void
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)
            ->post("/admin/users/{$this->user->id}/invitation-limit", [
                'invitation_limit' => 50,
            ]);

        // Should be forbidden or redirected
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302,
        );

        // Verify limit was not updated
        $this->user->refresh();
        $this->assertNull($this->user->invitation_limit);
    }

    #[Test]
    public function non_admin_cannot_reset_invitation_limit(): void
    {
        $regularUser = User::factory()->create();

        $this->user->invitation_limit = 50;
        $this->user->save();

        $response = $this->actingAs($regularUser)
            ->post("/admin/users/{$this->user->id}/invitation-limit/reset");

        // Should be forbidden or redirected
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302,
        );

        // Verify limit was not reset
        $this->user->refresh();
        $this->assertEquals(50, $this->user->invitation_limit);
    }

    #[Test]
    public function updating_limit_validates_required_field(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$this->user->id}/invitation-limit", [
                // Missing invitation_limit field
            ]);

        $response->assertSessionHasErrors('invitation_limit');

        // Verify limit was not updated
        $this->user->refresh();
        $this->assertNull($this->user->invitation_limit);
    }

    #[Test]
    public function updating_limit_validates_integer_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$this->user->id}/invitation-limit", [
                'invitation_limit' => 'not-a-number',
            ]);

        $response->assertSessionHasErrors('invitation_limit');
    }

    #[Test]
    public function updating_limit_validates_minimum_value(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/admin/users/{$this->user->id}/invitation-limit", [
                'invitation_limit' => -1,
            ]);

        $response->assertSessionHasErrors('invitation_limit');
    }

    #[Test]
    public function guest_cannot_update_invitation_limit(): void
    {
        $response = $this->post("/admin/users/{$this->user->id}/invitation-limit", [
            'invitation_limit' => 50,
        ]);

        // Should redirect to login
        $response->assertRedirect();

        // Verify limit was not updated
        $this->user->refresh();
        $this->assertNull($this->user->invitation_limit);
    }

    #[Test]
    public function guest_cannot_reset_invitation_limit(): void
    {
        $this->user->invitation_limit = 50;
        $this->user->save();

        $response = $this->post("/admin/users/{$this->user->id}/invitation-limit/reset");

        // Should redirect to login
        $response->assertRedirect();

        // Verify limit was not reset
        $this->user->refresh();
        $this->assertEquals(50, $this->user->invitation_limit);
    }

    #[Test]
    public function admin_can_view_user_invitations_on_profile(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/admin/users/{$this->user->id}");

        $response->assertStatus(200);

        // Should contain invitation-related elements
        $response->assertSee('Invitations');
        $response->assertSee('invitation-limit');
    }

    #[Test]
    public function custom_limit_overrides_karma_based_calculation(): void
    {
        // User has 100 karma = 10 invitations normally
        $this->assertEquals(10, $this->user->getInvitationLimit());

        // Set custom limit
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->user->id}/invitation-limit", [
                'invitation_limit' => 25,
            ]);

        // Should now use custom limit
        $this->user->refresh();
        $this->assertEquals(25, $this->user->getInvitationLimit());
    }

    #[Test]
    public function resetting_limit_returns_to_karma_based_calculation(): void
    {
        // Set custom limit
        $this->user->invitation_limit = 25;
        $this->user->save();
        $this->assertEquals(25, $this->user->getInvitationLimit());

        // Reset to default
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->user->id}/invitation-limit/reset");

        // Should now use karma-based (100 karma = 10 invitations)
        $this->user->refresh();
        $this->assertEquals(10, $this->user->getInvitationLimit());
    }
}
