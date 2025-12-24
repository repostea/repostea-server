<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Comment;
use App\Models\KarmaLevel;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase as BaseTestCase;

final class ApiTestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $user;

    protected $token;

    protected $admin;

    protected $adminToken;

    protected $category;

    protected $post;

    protected $comment;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar cache para evitar interferencias
        Cache::flush();

        // Configurar niveles de karma
        $this->setupKarmaLevels();

        // Create standard user for tests
        $this->user = $this->createUser();
        $this->token = $this->user->createToken('auth_token')->plainTextToken;

        $this->admin = $this->createUser(['is_admin' => true]);
        $this->adminToken = $this->admin->createToken('auth_token')->plainTextToken;

        // Create category for tests
        $this->category = Category::factory()->create();

        $this->post = Post::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $this->comment = Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
        ]);
    }

    /**
     * Crear usuario para pruebas.
     *
     * @param  array  $attributes
     *
     * @return User
     */
    protected function createUser($attributes = [])
    {
        $user = User::factory()->create($attributes);

        // Assign basic karma level
        $user->highest_level_id = KarmaLevel::where('required_karma', 0)->first()->id;
        $user->save();

        return $user;
    }

    /**
     * Configurar niveles de karma para pruebas.
     */
    protected function setupKarmaLevels(): void
    {
        // Solo crear niveles si no existen
        if (KarmaLevel::count() === 0) {
            KarmaLevel::create([
                'name' => 'Novice',
                'badge' => 'badge-novice',
                'required_karma' => 0,
            ]);

            KarmaLevel::create([
                'name' => 'Intermediate',
                'badge' => 'badge-intermediate',
                'required_karma' => 50,
            ]);

            KarmaLevel::create([
                'name' => 'Advanced',
                'badge' => 'badge-advanced',
                'required_karma' => 150,
            ]);

            KarmaLevel::create([
                'name' => 'Expert',
                'badge' => 'badge-expert',
                'required_karma' => 500,
            ]);

            KarmaLevel::create([
                'name' => 'Master',
                'badge' => 'badge-master',
                'required_karma' => 1000,
            ]);
        }
    }

    /**
     * Realizar solicitud autenticada como usuario estándar.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $data
     *
     * @return \Illuminate\Testing\TestResponse
     */
    protected function authRequest($method, $uri, $data = [])
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->json($method, $uri, $data);
    }

    /**
     * Realizar solicitud autenticada como administrador.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $data
     *
     * @return \Illuminate\Testing\TestResponse
     */
    protected function authAdminRequest($method, $uri, $data = [])
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json($method, $uri, $data);
    }

    /**
     * Realizar solicitud GET autenticada.
     *
     * @param  string  $uri
     * @param  array  $data
     *
     * @return \Illuminate\Testing\TestResponse
     */
    protected function authGet($uri, $data = [])
    {
        return $this->authRequest('GET', $uri, $data);
    }

    /**
     * Realizar solicitud POST autenticada.
     *
     * @param  string  $uri
     * @param  array  $data
     *
     * @return \Illuminate\Testing\TestResponse
     */
    protected function authPost($uri, $data = [])
    {
        return $this->authRequest('POST', $uri, $data);
    }

    /**
     * Realizar solicitud PUT autenticada.
     *
     * @param  string  $uri
     * @param  array  $data
     *
     * @return \Illuminate\Testing\TestResponse
     */
    protected function authPut($uri, $data = [])
    {
        return $this->authRequest('PUT', $uri, $data);
    }

    /**
     * Realizar solicitud DELETE autenticada.
     *
     * @param  string  $uri
     * @param  array  $data
     *
     * @return \Illuminate\Testing\TestResponse
     */
    protected function authDelete($uri, $data = [])
    {
        return $this->authRequest('DELETE', $uri, $data);
    }

    /**
     * Verificar que se requiere autenticación para una ruta.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $data
     */
    protected function assertAuthenticationRequired($method, $uri, $data = []): void
    {
        $this->json($method, $uri, $data)
            ->assertStatus(401);
    }

    /**
     * Crear un post para pruebas con datos específicos.
     *
     * @param  array  $attributes
     *
     * @return Post
     */
    protected function createPost($attributes = [])
    {
        $defaultAttributes = [
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'title' => 'Test Post Title',
            'content' => 'Test post content',
            'type' => 'article',
            'content_type' => 'text',
        ];

        return Post::factory()->create(array_merge($defaultAttributes, $attributes));
    }

    /**
     * Crear un comentario para pruebas con datos específicos.
     *
     * @param  array  $attributes
     *
     * @return Comment
     */
    protected function createComment($attributes = [])
    {
        $defaultAttributes = [
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
            'content' => 'Test comment content',
        ];

        return Comment::factory()->create(array_merge($defaultAttributes, $attributes));
    }
}
