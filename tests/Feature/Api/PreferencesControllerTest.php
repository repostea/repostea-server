<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserPreference;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PreferencesControllerTest extends TestCase
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
    public function it_returns_default_preferences_for_unauthenticated_users(): void
    {
        $response = $this->getJson('/api/v1/preferences');

        $response->assertStatus(200)
            ->assertJson([
                'layout' => 'card',
                'theme' => 'renegados1',
                'sort_by' => 'created_at',
                'sort_dir' => 'desc',
                'filters' => null,
                'content_languages' => null,
                'push_notifications' => null,
            ]);
    }

    #[Test]
    public function it_creates_default_preferences_for_authenticated_user(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/preferences');

        $response->assertStatus(200)
            ->assertJson([
                'layout' => 'card',
                'theme' => 'renegados1',
                'sort_by' => 'created_at',
                'sort_dir' => 'desc',
            ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_can_save_content_languages_preference(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preferences', [
                'content_languages' => ['es', 'en', 'fr'],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Preferences saved successfully',
            ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
        ]);

        $preference = UserPreference::where('user_id', $this->user->id)->first();
        $this->assertEquals(['es', 'en', 'fr'], $preference->content_languages);
    }

    #[Test]
    public function it_can_update_existing_content_languages(): void
    {
        // Create initial preference
        UserPreference::create([
            'user_id' => $this->user->id,
            'content_languages' => ['es'],
        ]);

        // Update with new languages
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preferences', [
                'content_languages' => ['es', 'en', 'de'],
            ]);

        $response->assertStatus(200);

        $preference = UserPreference::where('user_id', $this->user->id)->first();
        $this->assertEquals(['es', 'en', 'de'], $preference->content_languages);
    }

    #[Test]
    public function it_can_clear_content_languages_by_setting_empty_array(): void
    {
        // Create preference with languages
        UserPreference::create([
            'user_id' => $this->user->id,
            'content_languages' => ['es', 'en'],
        ]);

        // Clear languages
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preferences', [
                'content_languages' => [],
            ]);

        $response->assertStatus(200);

        $preference = UserPreference::where('user_id', $this->user->id)->first();
        $this->assertEquals([], $preference->content_languages);
    }

    #[Test]
    public function it_validates_content_languages_as_array(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preferences', [
                'content_languages' => 'not-an-array',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content_languages']);
    }

    #[Test]
    public function it_can_save_content_languages_with_other_preferences(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preferences', [
                'theme' => 'dark',
                'layout' => 'list',
                'content_languages' => ['es', 'ca'],
            ]);

        $response->assertStatus(200);

        $preference = UserPreference::where('user_id', $this->user->id)->first();
        $this->assertEquals('dark', $preference->theme);
        $this->assertEquals('list', $preference->layout);
        $this->assertEquals(['es', 'ca'], $preference->content_languages);
    }

    #[Test]
    public function it_returns_content_languages_in_get_preferences(): void
    {
        UserPreference::create([
            'user_id' => $this->user->id,
            'theme' => 'yups',
            'layout' => 'compact',
            'content_languages' => ['es', 'en', 'fr'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/preferences');

        $response->assertStatus(200)
            ->assertJson([
                'theme' => 'yups',
                'layout' => 'compact',
                'content_languages' => ['es', 'en', 'fr'],
            ]);
    }

    #[Test]
    public function it_preserves_other_preferences_when_updating_content_languages(): void
    {
        // Create initial preferences
        UserPreference::create([
            'user_id' => $this->user->id,
            'theme' => 'barrapunto',
            'layout' => 'card',
            'sort_by' => 'votes',
            'content_languages' => ['es'],
        ]);

        // Update only content_languages
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preferences', [
                'content_languages' => ['es', 'en', 'fr', 'de'],
            ]);

        $response->assertStatus(200);

        $preference = UserPreference::where('user_id', $this->user->id)->first();

        // Other preferences should remain unchanged
        $this->assertEquals('barrapunto', $preference->theme);
        $this->assertEquals('card', $preference->layout);
        $this->assertEquals('votes', $preference->sort_by);

        // content_languages should be updated
        $this->assertEquals(['es', 'en', 'fr', 'de'], $preference->content_languages);
    }

    #[Test]
    public function it_requires_authentication_to_save_preferences(): void
    {
        $response = $this->postJson('/api/v1/preferences', [
            'content_languages' => ['es', 'en'],
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'User not authenticated',
            ]);
    }

    #[Test]
    public function it_handles_null_content_languages(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preferences', [
                'content_languages' => null,
            ]);

        $response->assertStatus(200);

        // When null is passed, the field should not be updated (filtered out)
        $preference = UserPreference::where('user_id', $this->user->id)->first();
        $this->assertNull($preference->content_languages);
    }

    #[Test]
    public function it_can_handle_large_language_arrays(): void
    {
        $languages = ['es', 'en', 'fr', 'de', 'it', 'pt', 'nl', 'ru', 'pl', 'sv', 'da', 'no', 'fi', 'ro', 'cs', 'hu'];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preferences', [
                'content_languages' => $languages,
            ]);

        $response->assertStatus(200);

        $preference = UserPreference::where('user_id', $this->user->id)->first();
        $this->assertEquals($languages, $preference->content_languages);
        $this->assertCount(16, $preference->content_languages);
    }

    #[Test]
    public function it_stores_content_languages_as_json_in_database(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preferences', [
                'content_languages' => ['es', 'ca', 'eu'],
            ]);

        // Check raw database value
        $rawValue = DB::table('user_preferences')
            ->where('user_id', $this->user->id)
            ->value('content_languages');

        // Should be stored as JSON string
        $this->assertJson($rawValue);

        // Should decode to the correct array
        $decoded = json_decode($rawValue, true);
        $this->assertEquals(['es', 'ca', 'eu'], $decoded);
    }
}
