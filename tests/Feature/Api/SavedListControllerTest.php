<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\SavedList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SavedListControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $token;

    protected $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('auth_token')->plainTextToken;

        $this->post = Post::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_can_list_user_saved_lists(): void
    {
        $lists = SavedList::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'type' => 'custom',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/lists');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'description', 'user_id', 'type', 'is_public',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_can_create_a_new_custom_list(): void
    {
        $listData = [
            'name' => 'My test list',
            'description' => 'Test list description',
            'is_public' => true,
            'type' => 'custom',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/lists', $listData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'My test list',
                    'description' => 'Test list description',
                    'is_public' => true,
                    'type' => 'custom',
                    'user_id' => $this->user->id,
                ],
            ]);

        $this->assertDatabaseHas('saved_lists', [
            'name' => 'My test list',
            'description' => 'Test list description',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_cannot_create_duplicate_special_lists(): void
    {
        SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'favorite',
            'name' => 'Favorites',
        ]);

        $listData = [
            'name' => 'Another favorites list',
            'type' => 'favorite',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/lists', $listData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'A list of this type already exists.',
            ]);
    }

    #[Test]
    public function it_can_show_a_specific_list(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'custom',
            'name' => 'List to view',
        ]);

        $list->posts()->attach($this->post->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/lists/{$list->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $list->id,
                    'name' => 'List to view',
                    'user_id' => $this->user->id,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'description', 'user_id', 'type', 'is_public', 'posts',
                ],
            ]);

        $response->assertJsonCount(1, 'data.posts');
    }

    #[Test]
    public function it_can_update_a_custom_list(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'custom',
            'name' => 'Original list',
            'description' => 'Original description',
            'is_public' => false,
        ]);

        $updateData = [
            'name' => 'Updated list',
            'description' => 'Updated description',
            'is_public' => true,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/lists/{$list->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $list->id,
                    'name' => 'Updated list',
                    'description' => 'Updated description',
                    'is_public' => true,
                ],
            ]);

        $this->assertDatabaseHas('saved_lists', [
            'id' => $list->id,
            'name' => 'Updated list',
            'description' => 'Updated description',
            'is_public' => true,
        ]);
    }

    #[Test]
    public function it_cannot_change_type_of_special_lists(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'favorite',
            'name' => 'Favorites',
        ]);

        $updateData = [
            'type' => 'custom',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/lists/{$list->id}", $updateData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot change type of special lists.',
            ]);
    }

    #[Test]
    public function it_can_delete_a_custom_list(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'custom',
            'name' => 'List to delete',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/lists/{$list->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'List deleted successfully.',
            ]);

        $this->assertDatabaseMissing('saved_lists', [
            'id' => $list->id,
        ]);
    }

    #[Test]
    public function it_cannot_delete_special_lists(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'favorite',
            'name' => 'Favorites',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/lists/{$list->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'This action is unauthorized.',
            ]);

        $this->assertDatabaseHas('saved_lists', [
            'id' => $list->id,
        ]);
    }

    #[Test]
    public function it_can_add_a_post_to_a_list(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'custom',
        ]);

        $data = [
            'post_id' => $this->post->id,
            'notes' => 'Notes about this post',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/lists/{$list->id}/posts", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Post added to list successfully.',
            ]);

        $this->assertDatabaseHas('saved_list_posts', [
            'saved_list_id' => $list->id,
            'post_id' => $this->post->id,
            'notes' => 'Notes about this post',
        ]);
    }

    #[Test]
    public function it_cannot_add_the_same_post_twice_to_a_list(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'custom',
        ]);

        $list->posts()->attach($this->post->id);

        $data = [
            'post_id' => $this->post->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/lists/{$list->id}/posts", $data);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Post is already in this list.',
            ]);
    }

    #[Test]
    public function it_can_remove_a_post_from_a_list(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'custom',
        ]);

        $list->posts()->attach($this->post->id);

        $data = [
            'post_id' => $this->post->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/lists/{$list->id}/posts", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Post removed from list successfully.',
            ]);

        $this->assertDatabaseMissing('saved_list_posts', [
            'saved_list_id' => $list->id,
            'post_id' => $this->post->id,
        ]);
    }

    #[Test]
    public function it_can_toggle_a_post_in_favorites(): void
    {
        $favoritesList = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'favorite',
            'name' => 'Favorites',
        ]);

        $data = [
            'post_id' => $this->post->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts/toggle-favorite', $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Post added to favorites.',
                'is_favorite' => true,
            ]);

        $this->assertDatabaseHas('saved_list_posts', [
            'saved_list_id' => $favoritesList->id,
            'post_id' => $this->post->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts/toggle-favorite', $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Post removed from favorites.',
                'is_favorite' => false,
            ]);

        $this->assertDatabaseMissing('saved_list_posts', [
            'saved_list_id' => $favoritesList->id,
            'post_id' => $this->post->id,
        ]);
    }

    #[Test]
    public function it_can_toggle_a_post_in_read_later(): void
    {
        $readLaterList = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'read_later',
            'name' => 'Read Later',
        ]);

        $data = [
            'post_id' => $this->post->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts/toggle-read-later', $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Post added to read later.',
                'is_read_later' => true,
            ]);

        $this->assertDatabaseHas('saved_list_posts', [
            'saved_list_id' => $readLaterList->id,
            'post_id' => $this->post->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts/toggle-read-later', $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Post removed from read later.',
                'is_read_later' => false,
            ]);

        $this->assertDatabaseMissing('saved_list_posts', [
            'saved_list_id' => $readLaterList->id,
            'post_id' => $this->post->id,
        ]);
    }

    #[Test]
    public function it_can_check_saved_status_of_a_post(): void
    {
        $favoritesList = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'favorite',
            'name' => 'Favorites',
        ]);

        $readLaterList = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'read_later',
            'name' => 'Read Later',
        ]);

        $customList = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'custom',
            'name' => 'My custom list',
        ]);

        $favoritesList->posts()->attach($this->post->id);
        $customList->posts()->attach($this->post->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/posts/{$this->post->id}/saved-status");

        $response->assertStatus(200)
            ->assertJson([
                'is_favorite' => true,
                'is_read_later' => false,
            ])
            ->assertJsonStructure([
                'is_favorite',
                'is_read_later',
                'saved_lists',
            ]);

        $response->assertJsonCount(1, 'saved_lists');
    }

    #[Test]
    public function it_can_update_notes_for_a_post_in_a_list(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'custom',
        ]);

        $list->posts()->attach($this->post->id, ['notes' => 'Original notes']);

        $data = [
            'post_id' => $this->post->id,
            'notes' => 'Updated notes',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/lists/{$list->id}/posts/notes", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Notes updated successfully.',
            ]);

        $this->assertDatabaseHas('saved_list_posts', [
            'saved_list_id' => $list->id,
            'post_id' => $this->post->id,
            'notes' => 'Updated notes',
        ]);
    }

    #[Test]
    public function it_can_clear_all_posts_from_a_custom_list(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'custom',
        ]);

        $posts = Post::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        foreach ($posts as $post) {
            $list->posts()->attach($post->id);
        }

        $this->assertEquals(3, $list->posts()->count());

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/lists/{$list->id}/posts/all");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'List cleared successfully.',
            ]);

        $this->assertEquals(0, $list->fresh()->posts()->count());
    }

    #[Test]
    public function it_cannot_clear_special_lists(): void
    {
        $list = SavedList::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'favorite',
        ]);

        $list->posts()->attach($this->post->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/lists/{$list->id}/posts/all");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot clear special system lists.',
            ]);

        $this->assertEquals(1, $list->fresh()->posts()->count());
    }
}
