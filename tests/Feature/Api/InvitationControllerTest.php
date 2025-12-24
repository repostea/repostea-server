<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InvitationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with verified email
        $this->user = User::factory()->create([
            'karma_points' => 100,
            'email_verified_at' => now(),
        ]);
        $this->token = $this->user->createToken('auth_token')->plainTextToken;
    }

    #[Test]
    public function it_can_list_user_invitations(): void
    {
        // Create some invitations for the user
        Invitation::factory()->count(3)->create([
            'created_by' => $this->user->id,
        ]);

        // Create invitation for another user (should not appear)
        $otherUser = User::factory()->create();
        Invitation::factory()->create([
            'created_by' => $otherUser->id,
        ]);

        // Make request
        $response = $this->getJson('/api/v1/invitations', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response
        $response->assertStatus(200)
            ->assertJsonCount(3, 'invitations')
            ->assertJsonStructure([
                'invitations' => [
                    '*' => [
                        'id',
                        'code',
                        'max_uses',
                        'current_uses',
                        'is_active',
                        'expires_at',
                        'created_at',
                        'registration_url',
                    ],
                ],
                'limit',
                'used',
                'remaining',
                'can_create' => [
                    'can',
                    'reason',
                ],
            ]);

        // Verify stats
        $data = $response->json();
        $this->assertEquals(3, $data['used']);
        $this->assertEquals(10, $data['limit']); // 100 karma = 10 invitations
        $this->assertEquals(7, $data['remaining']);
        $this->assertTrue($data['can_create']['can']);
    }

    #[Test]
    public function it_can_create_invitation(): void
    {
        // Make request
        $response = $this->postJson('/api/v1/invitations', [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response
        $response->assertStatus(201)
            ->assertJsonStructure([
                'invitation' => [
                    'id',
                    'code',
                    'max_uses',
                    'current_uses',
                    'is_active',
                    'expires_at',
                    'created_at',
                    'registration_url',
                ],
                'remaining',
                'message',
            ]);

        // Verify invitation was created
        $data = $response->json();
        $this->assertDatabaseHas('invitations', [
            'code' => $data['invitation']['code'],
            'created_by' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_can_create_invitation_with_custom_parameters(): void
    {
        // Make request with custom parameters
        $response = $this->postJson('/api/v1/invitations', [
            'max_uses' => 5,
            'expires_in_days' => 7,
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response
        $response->assertStatus(201);

        // Verify invitation was created with correct parameters
        $data = $response->json();
        $this->assertEquals(5, $data['invitation']['max_uses']);

        $invitation = Invitation::where('code', $data['invitation']['code'])->first();
        $this->assertNotNull($invitation);
        $this->assertEquals(5, $invitation->max_uses);
        $this->assertTrue($invitation->expires_at->diffInDays(now()) <= 7);
    }

    #[Test]
    public function it_prevents_creation_when_limit_reached(): void
    {
        // Create invitations up to the limit
        $limit = $this->user->getInvitationLimit();
        Invitation::factory()->count($limit)->create([
            'created_by' => $this->user->id,
        ]);

        // Try to create another invitation
        $response = $this->postJson('/api/v1/invitations', [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Invitation limit reached',
                'error' => 'cannot_create_invitation',
            ]);
    }

    #[Test]
    public function it_prevents_guest_users_from_creating_invitations(): void
    {
        // Create guest user
        $guestUser = User::factory()->create([
            'is_guest' => true,
            'email_verified_at' => now(),
        ]);
        $guestToken = $guestUser->createToken('auth_token')->plainTextToken;

        // Try to create invitation
        $response = $this->postJson('/api/v1/invitations', [], [
            'Authorization' => 'Bearer ' . $guestToken,
        ]);

        // Verify response
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Guest users cannot create invitations',
                'error' => 'cannot_create_invitation',
            ]);
    }

    #[Test]
    public function it_prevents_unverified_users_from_creating_invitations(): void
    {
        // Create unverified user
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
        ]);
        $unverifiedToken = $unverifiedUser->createToken('auth_token')->plainTextToken;

        // Try to create invitation
        $response = $this->postJson('/api/v1/invitations', [], [
            'Authorization' => 'Bearer ' . $unverifiedToken,
        ]);

        // Verify response
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Email verification required',
                'error' => 'cannot_create_invitation',
            ]);
    }

    #[Test]
    public function it_can_delete_unused_invitation(): void
    {
        // Create invitation
        $invitation = Invitation::factory()->create([
            'created_by' => $this->user->id,
            'current_uses' => 0,
        ]);

        // Delete invitation
        $response = $this->deleteJson("/api/v1/invitations/{$invitation->id}", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Invitation deleted successfully.',
            ]);

        // Verify invitation was deleted
        $this->assertDatabaseMissing('invitations', [
            'id' => $invitation->id,
        ]);
    }

    #[Test]
    public function it_prevents_deleting_used_invitation(): void
    {
        // Create used invitation
        $invitation = Invitation::factory()->create([
            'created_by' => $this->user->id,
            'current_uses' => 1,
        ]);

        // Try to delete invitation
        $response = $this->deleteJson("/api/v1/invitations/{$invitation->id}", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Cannot delete an invitation that has been used.',
            ]);

        // Verify invitation still exists
        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
        ]);
    }

    #[Test]
    public function it_prevents_deleting_other_users_invitation(): void
    {
        // Create invitation for another user
        $otherUser = User::factory()->create();
        $invitation = Invitation::factory()->create([
            'created_by' => $otherUser->id,
        ]);

        // Try to delete invitation
        $response = $this->deleteJson("/api/v1/invitations/{$invitation->id}", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You do not have permission to delete this invitation.',
            ]);

        // Verify invitation still exists
        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
        ]);
    }

    #[Test]
    public function it_shows_unlimited_for_admin_users(): void
    {
        // Create admin role
        $adminRole = Role::factory()->create(['slug' => 'admin']);
        $this->user->roles()->attach($adminRole);

        // Make request
        $response = $this->getJson('/api/v1/invitations', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response shows unlimited
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals('unlimited', $data['limit']);
        $this->assertEquals('unlimited', $data['remaining']);
    }

    #[Test]
    public function it_respects_custom_invitation_limit(): void
    {
        // Set custom limit
        $this->user->invitation_limit = 25;
        $this->user->save();

        // Make request
        $response = $this->getJson('/api/v1/invitations', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response shows custom limit
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(25, $data['limit']);
        $this->assertEquals(25, $data['remaining']);
    }

    #[Test]
    public function it_validates_max_uses_parameter(): void
    {
        // Try to create invitation with invalid max_uses
        $response = $this->postJson('/api/v1/invitations', [
            'max_uses' => 100, // Max is 10
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response
        $response->assertStatus(422);
    }

    #[Test]
    public function it_validates_expires_in_days_parameter(): void
    {
        // Try to create invitation with invalid expires_in_days
        $response = $this->postJson('/api/v1/invitations', [
            'expires_in_days' => 1000, // Max is 365
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // Verify response
        $response->assertStatus(422);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        // Try to list invitations without auth
        $response = $this->getJson('/api/v1/invitations');
        $response->assertStatus(401);

        // Try to create invitation without auth
        $response = $this->postJson('/api/v1/invitations');
        $response->assertStatus(401);

        // Try to delete invitation without auth
        $invitation = Invitation::factory()->create();
        $response = $this->deleteJson("/api/v1/invitations/{$invitation->id}");
        $response->assertStatus(401);
    }
}
