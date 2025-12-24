<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

final class VoteFactory extends Factory
{
    protected $model = Vote::class;

    public function definition(): array
    {
        $votableType = fake()->randomElement([Post::class, Comment::class]);
        $votable = $votableType::factory();
        $value = fake()->randomElement([1, 1, 1, -1]); // 75% probability of positive vote

        // Assign vote type based on value
        $type = null;
        if ($value > 0) {
            $type = fake()->randomElement(Vote::getValidPositiveTypes());
        } else {
            $type = fake()->randomElement(Vote::getValidNegativeTypes());
        }

        return [
            'user_id' => User::factory(),
            'votable_id' => $votable,
            'votable_type' => $votableType,
            'value' => $value,
            'type' => $type,
        ];
    }

    public function forPost(): static
    {
        return $this->state(function (array $attributes) {
            $post = Post::inRandomOrder()->first() ?? Post::factory()->create();

            return [
                'votable_id' => $post->id,
                'votable_type' => Post::class,
            ];
        });
    }

    public function forComment(): static
    {
        return $this->state(function (array $attributes) {
            $comment = Comment::inRandomOrder()->first() ?? Comment::factory()->create();

            return [
                'votable_id' => $comment->id,
                'votable_type' => Comment::class,
            ];
        });
    }

    public function upvote(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => 1,
            'type' => fake()->randomElement(Vote::getValidPositiveTypes()),
        ]);
    }

    public function downvote(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => -1,
            'type' => fake()->randomElement(Vote::getValidNegativeTypes()),
        ]);
    }

    public function withSpecificType($type): static
    {
        return $this->state(function (array $attributes) use ($type) {
            $isPositiveType = in_array($type, Vote::getValidPositiveTypes());
            $value = $isPositiveType ? 1 : -1;

            return [
                'value' => $value,
                'type' => $type,
            ];
        });
    }
}
