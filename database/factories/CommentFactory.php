<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => fake()->paragraphs(rand(1, 3), true),
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'parent_id' => null,
            'votes_count' => fake()->numberBetween(-5, 30),
        ];
    }

    /**
     * State para respuestas a otros comentarios.
     */
    public function reply(): static
    {
        return $this->state(function (array $attributes) {
            // Si hay un comentario existente, usarlo como padre
            $parentComment = Comment::whereNull('parent_id')->inRandomOrder()->first();

            if ($parentComment) {
                return [
                    'parent_id' => $parentComment->id,
                    'post_id' => $parentComment->post_id,
                ];
            }

            // Si no hay comentario padre, crear uno
            $post = Post::inRandomOrder()->first() ?? Post::factory()->create();
            $parentComment = Comment::factory()->create([
                'post_id' => $post->id,
                'parent_id' => null,
            ]);

            return [
                'parent_id' => $parentComment->id,
                'post_id' => $post->id,
            ];
        });
    }

    /**
     * State para comentarios populares con muchos votos positivos.
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'votes_count' => fake()->numberBetween(20, 100),
        ]);
    }
}
