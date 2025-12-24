<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GuestUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_login_creates_user_with_beautiful_names(): void
    {
        // Make guest login request
        $response = $this->postJson('/api/v1/guest-login');

        // Verify response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'username',
                    'display_name',
                    'bio',
                    'avatar',
                    'karma_points',
                    'created_at',
                ],
                'token',
            ]);

        $userData = $response->json('user');
        $userId = $userData['id'];

        // Verify user was created in database
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'is_guest' => true,
        ]);

        // Get the created user
        $user = User::find($userId);

        // Critical: Verify display_name is NOT generic
        $this->assertNotNull($user->display_name);
        $this->assertNotEquals('Guest User', $user->display_name);
        $this->assertNotEquals('Anonymous', $user->display_name);
        $this->assertNotEquals('User', $user->display_name);

        // Critical: Verify username is NOT generic pattern
        $this->assertNotNull($user->username);
        $this->assertStringNotContainsString('guest_', $user->username);
        $this->assertStringNotContainsString('anonymous_', $user->username);

        // Verify beautiful name characteristics
        $this->assertGreaterThan(2, strlen($user->display_name));
        $this->assertMatchesRegularExpression('/^[A-Za-z\s]+$/', $user->display_name);

        // Verify username format (name_number or name_adjective_number)
        $this->assertMatchesRegularExpression('/^[a-z]+(_[a-z]+)?_\d{3}$/', $user->username);
    }

    #[Test]
    public function guest_users_have_unique_names_and_usernames(): void
    {
        $users = [];

        // Create multiple guest users
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/guest-login');
            $response->assertStatus(200);

            $userData = $response->json('user');
            $users[] = [
                'display_name' => $userData['display_name'],
                'username' => $userData['username'],
            ];
        }

        // Extract all display names and usernames
        $displayNames = array_column($users, 'display_name');
        $usernames = array_column($users, 'username');

        // Verify all usernames are unique (critical for login)
        $this->assertEquals(
            count($usernames),
            count(array_unique($usernames)),
            'All guest usernames must be unique',
        );

        // Verify some variety in display names (not all identical)
        $this->assertGreaterThan(
            1,
            count(array_unique($displayNames)),
            'Guest display names should have some variety',
        );
    }

    #[Test]
    public function guest_user_fields_are_properly_populated(): void
    {
        $response = $this->postJson('/api/v1/guest-login');
        $response->assertStatus(200);

        $userId = $response->json('user.id');
        $user = User::find($userId);

        // Verify essential fields are populated
        $this->assertTrue($user->is_guest);
        $this->assertNotNull($user->display_name);
        $this->assertNotNull($user->username);
        $this->assertNotNull($user->email);
        $this->assertNotNull($user->password);
        $this->assertNotNull($user->email_verified_at);

        // Verify guest-specific characteristics
        $this->assertStringEndsWith('@temp.local', $user->email);
        $this->assertTrue($user->email_verified_at->isToday());
    }

    #[Test]
    public function guest_user_can_be_used_for_authentication(): void
    {
        // Create guest user
        $response = $this->postJson('/api/v1/guest-login');
        $response->assertStatus(200);

        $token = $response->json('token');
        $userId = $response->json('user.id');

        // Verify token works for authenticated requests
        $authResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user');

        $authResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $userId,
                ],
            ]);
    }

    #[Test]
    public function guest_user_display_name_appears_in_api_responses(): void
    {
        // Create guest user
        $response = $this->postJson('/api/v1/guest-login');
        $response->assertStatus(200);

        $displayName = $response->json('user.display_name');
        $token = $response->json('token');

        // Verify display_name is returned in user API
        $this->assertNotNull($displayName);
        $this->assertNotEquals('Guest User', $displayName);

        // Verify display_name appears in authenticated user endpoint
        $userResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user');

        $userResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'display_name' => $displayName,
                ],
            ]);
    }

    #[Test]
    public function guest_users_do_not_have_email_conflicts(): void
    {
        $emails = [];

        // Create multiple guest users
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/guest-login');
            $response->assertStatus(200);

            $userId = $response->json('user.id');
            $user = User::find($userId);
            $emails[] = $user->email;
        }

        // Verify all emails are unique
        $this->assertEquals(
            count($emails),
            count(array_unique($emails)),
            'All guest emails must be unique to prevent conflicts',
        );

        // Verify all emails follow expected pattern
        foreach ($emails as $email) {
            $this->assertStringStartsWith('guest_', $email);
            $this->assertStringEndsWith('@temp.local', $email);
        }
    }

    #[Test]
    public function guest_login_regression_test_for_beautiful_names(): void
    {
        // This test specifically prevents regression to ugly names
        $response = $this->postJson('/api/v1/guest-login');
        $response->assertStatus(200);

        $user = $response->json('user');

        // CRITICAL: These patterns should NEVER appear again
        $forbiddenPatterns = [
            'Guest User',
            'guest_[A-Za-z0-9]{8}', // Old random pattern
            'Anonymous',
            'anonymous_',
            'User_\d+',
            'TempUser',
            'Guest',
        ];

        foreach ($forbiddenPatterns as $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                "/{$pattern}/",
                $user['display_name'] ?? '',
                "Display name should not match forbidden pattern: {$pattern}",
            );

            $this->assertDoesNotMatchRegularExpression(
                "/{$pattern}/",
                $user['username'] ?? '',
                "Username should not match forbidden pattern: {$pattern}",
            );
        }

        // POSITIVE: Should match beautiful name patterns
        $this->assertMatchesRegularExpression(
            '/^[A-Z][a-z]+(\s+(the\s+)?[A-Z][a-z]+)?$/',
            $user['display_name'],
            'Display name should match beautiful name pattern (e.g., "Marcus", "Wise Athena", "Diana the Bold")',
        );

        $this->assertMatchesRegularExpression(
            '/^[a-z]+(_[a-z]+)?_\d{3}$/',
            $user['username'],
            'Username should match beautiful pattern (e.g., "marcus_123", "athena_serene_456")',
        );
    }
}
