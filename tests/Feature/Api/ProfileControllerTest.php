<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'username' => 'test_user',
            'email' => 'test@example.com',
        ]);

        $this->token = $this->user->createToken('auth_token')->plainTextToken;
    }

    #[Test]
    public function it_requires_authentication_to_access_profile(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_can_show_user_profile(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'id' => $this->user->id,
                    'username' => 'test_user',
                ],
            ]);
    }

    #[Test]
    public function it_validates_profile_update_input(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/profile', [
                'username' => '',
                'email' => 'not-an-email',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'email']);
    }

    #[Test]
    public function it_can_update_user_profile(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/profile', [
                'username' => 'updated_username',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'updated_username',
        ]);
    }
}
