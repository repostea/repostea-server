<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_an_invitation(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'ABC123XYZ456',
            'created_by' => $creator->id,
            'expires_at' => now()->addDays(7),
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Invitation::class, $invitation);
        $this->assertEquals('ABC123XYZ456', $invitation->code);
        $this->assertEquals($creator->id, $invitation->created_by);
        $this->assertEquals(5, $invitation->max_uses);
        $this->assertEquals(0, $invitation->current_uses);
        $this->assertTrue($invitation->is_active);
    }

    public function test_it_belongs_to_creator(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'TEST123',
            'created_by' => $creator->id,
            'max_uses' => 1,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(User::class, $invitation->creator);
        $this->assertEquals($creator->id, $invitation->creator->id);
    }

    public function test_it_belongs_to_user_when_used(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'TEST456',
            'created_by' => $creator->id,
            'used_by' => $user->id,
            'used_at' => now(),
            'max_uses' => 1,
            'current_uses' => 1,
            'is_active' => false,
        ]);

        $this->assertInstanceOf(User::class, $invitation->user);
        $this->assertEquals($user->id, $invitation->user->id);
    }

    public function test_it_generates_unique_code(): void
    {
        $code1 = Invitation::generateCode();
        $code2 = Invitation::generateCode();

        $this->assertIsString($code1);
        $this->assertIsString($code2);
        $this->assertEquals(16, strlen($code1));
        $this->assertEquals(16, strlen($code2));
        $this->assertNotEquals($code1, $code2);
    }

    public function test_it_generates_code_with_custom_length(): void
    {
        $code = Invitation::generateCode(32);

        $this->assertEquals(32, strlen($code));
    }

    public function test_it_generates_code_not_already_existing(): void
    {
        $creator = User::factory()->create();

        // Create invitation with specific code
        Invitation::create([
            'code' => 'EXISTING123',
            'created_by' => $creator->id,
            'max_uses' => 1,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        // Generate new code should not match existing
        $newCode = Invitation::generateCode();
        $this->assertNotEquals('EXISTING123', $newCode);
    }

    public function test_it_validates_active_invitation(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'VALID123',
            'created_by' => $creator->id,
            'expires_at' => now()->addDays(7),
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $this->assertTrue($invitation->isValid());
    }

    public function test_it_invalidates_inactive_invitation(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'INACTIVE123',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => false,
        ]);

        $this->assertFalse($invitation->isValid());
    }

    public function test_it_invalidates_expired_invitation(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'EXPIRED123',
            'created_by' => $creator->id,
            'expires_at' => now()->subDay(),
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $this->assertFalse($invitation->isValid());
    }

    public function test_it_invalidates_invitation_with_max_uses_reached(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'MAXED123',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 5,
            'is_active' => true,
        ]);

        $this->assertFalse($invitation->isValid());
    }

    public function test_it_validates_invitation_without_expiration(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'NOEXPIRY123',
            'created_by' => $creator->id,
            'expires_at' => null,
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $this->assertTrue($invitation->isValid());
    }

    public function test_it_marks_invitation_as_used(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'USE123',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $result = $invitation->markAsUsed($user->id);

        $this->assertTrue($result);
        $this->assertEquals(1, $invitation->current_uses);
        $this->assertEquals($user->id, $invitation->used_by);
        $this->assertNotNull($invitation->used_at);
        $this->assertTrue($invitation->is_active);
    }

    public function test_it_increments_current_uses_on_multiple_uses(): void
    {
        $creator = User::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'MULTI123',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $invitation->markAsUsed($user1->id);
        $invitation->markAsUsed($user2->id);

        $this->assertEquals(2, $invitation->current_uses);
        $this->assertEquals($user1->id, $invitation->used_by);
    }

    public function test_it_deactivates_when_max_uses_reached(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'SINGLE123',
            'created_by' => $creator->id,
            'max_uses' => 1,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $invitation->markAsUsed($user->id);

        $this->assertEquals(1, $invitation->current_uses);
        $this->assertFalse($invitation->is_active);
    }

    public function test_it_cannot_mark_invalid_invitation_as_used(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'INVALID123',
            'created_by' => $creator->id,
            'max_uses' => 1,
            'current_uses' => 1,
            'is_active' => false,
        ]);

        $result = $invitation->markAsUsed($user->id);

        $this->assertFalse($result);
        $this->assertEquals(1, $invitation->current_uses);
    }

    public function test_it_finds_valid_invitation_by_code(): void
    {
        $creator = User::factory()->create();

        Invitation::create([
            'code' => 'FINDME123',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $found = Invitation::findValidByCode('FINDME123');

        $this->assertInstanceOf(Invitation::class, $found);
        $this->assertEquals('FINDME123', $found->code);
    }

    public function test_it_returns_null_for_invalid_code(): void
    {
        $found = Invitation::findValidByCode('NONEXISTENT');

        $this->assertNull($found);
    }

    public function test_it_returns_null_for_expired_code(): void
    {
        $creator = User::factory()->create();

        Invitation::create([
            'code' => 'EXPIRED456',
            'created_by' => $creator->id,
            'expires_at' => now()->subDay(),
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $found = Invitation::findValidByCode('EXPIRED456');

        $this->assertNull($found);
    }

    public function test_active_scope_returns_only_active_invitations(): void
    {
        $creator = User::factory()->create();

        Invitation::create([
            'code' => 'ACTIVE1',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        Invitation::create([
            'code' => 'INACTIVE1',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 5,
            'is_active' => false,
        ]);

        $activeInvitations = Invitation::active()->get();

        $this->assertEquals(1, $activeInvitations->count());
        $this->assertEquals('ACTIVE1', $activeInvitations->first()->code);
    }

    public function test_valid_scope_returns_only_valid_invitations(): void
    {
        $creator = User::factory()->create();

        // Valid invitation
        Invitation::create([
            'code' => 'VALID1',
            'created_by' => $creator->id,
            'expires_at' => now()->addDays(7),
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        // Expired invitation
        Invitation::create([
            'code' => 'EXPIRED1',
            'created_by' => $creator->id,
            'expires_at' => now()->subDay(),
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        // Maxed out invitation
        Invitation::create([
            'code' => 'MAXED1',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 5,
            'is_active' => true,
        ]);

        $validInvitations = Invitation::valid()->get();

        $this->assertEquals(1, $validInvitations->count());
        $this->assertEquals('VALID1', $validInvitations->first()->code);
    }

    public function test_available_scope_returns_available_invitations(): void
    {
        $creator = User::factory()->create();

        Invitation::create([
            'code' => 'AVAILABLE1',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        Invitation::create([
            'code' => 'UNAVAILABLE1',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 5,
            'is_active' => false,
        ]);

        $availableInvitations = Invitation::available()->get();

        $this->assertEquals(1, $availableInvitations->count());
        $this->assertEquals('AVAILABLE1', $availableInvitations->first()->code);
    }

    public function test_it_casts_expires_at_to_datetime(): void
    {
        $creator = User::factory()->create();
        $expiresAt = now()->addDays(7);

        $invitation = Invitation::create([
            'code' => 'CAST123',
            'created_by' => $creator->id,
            'expires_at' => $expiresAt,
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $invitation->expires_at);
    }

    public function test_it_casts_used_at_to_datetime(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();
        $usedAt = now();

        $invitation = Invitation::create([
            'code' => 'USED789',
            'created_by' => $creator->id,
            'used_by' => $user->id,
            'used_at' => $usedAt,
            'max_uses' => 5,
            'current_uses' => 1,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $invitation->used_at);
    }

    public function test_it_casts_max_uses_to_integer(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'INT123',
            'created_by' => $creator->id,
            'max_uses' => '10',
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $this->assertIsInt($invitation->max_uses);
        $this->assertEquals(10, $invitation->max_uses);
    }

    public function test_it_casts_current_uses_to_integer(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'INT456',
            'created_by' => $creator->id,
            'max_uses' => 10,
            'current_uses' => '3',
            'is_active' => true,
        ]);

        $this->assertIsInt($invitation->current_uses);
        $this->assertEquals(3, $invitation->current_uses);
    }

    public function test_it_casts_is_active_to_boolean(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'BOOL123',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => '1',
        ]);

        $this->assertIsBool($invitation->is_active);
        $this->assertTrue($invitation->is_active);
    }

    public function test_it_has_timestamps(): void
    {
        $creator = User::factory()->create();

        $invitation = Invitation::create([
            'code' => 'TIME123',
            'created_by' => $creator->id,
            'max_uses' => 5,
            'current_uses' => 0,
            'is_active' => true,
        ]);

        $this->assertNotNull($invitation->created_at);
        $this->assertNotNull($invitation->updated_at);
    }
}
