<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApiPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_update_password_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current_password'),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/user/password', [
                'current_password' => 'current_password',
                'password' => 'new_password123',
                'password_confirmation' => 'new_password123',
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('new_password123', $user->password));
    }

    #[Test]
    public function user_cannot_update_password_with_invalid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current_password'),
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/user/password', [
                'current_password' => 'wrong_current_password',
                'password' => 'new_password123',
                'password_confirmation' => 'new_password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertTrue(Hash::check('current_password', $user->password));
    }

    #[Test]
    public function user_cannot_update_password_with_mismatched_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current_password'),
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/user/password', [
                'current_password' => 'current_password',
                'password' => 'new_password123',
                'password_confirmation' => 'different_password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $user->refresh();
        $this->assertTrue(Hash::check('current_password', $user->password));
    }

    #[Test]
    public function it_returns_error_for_unauthenticated_user(): void
    {
        $response = $this->putJson('/api/v1/user/password', [
            'current_password' => 'current_password',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(401);
    }
}
