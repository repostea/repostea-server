<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgoraMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgoraMessage>
 */
final class AgoraMessageFactory extends Factory
{
    protected $model = AgoraMessage::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'content' => fake()->paragraph(),
            'votes_count' => 0,
            'replies_count' => 0,
            'is_anonymous' => false,
            'language_code' => 'es',
        ];
    }

    /**
     * Indicate that the message is anonymous.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => true,
        ]);
    }

    /**
     * Indicate that the message is a reply to another message.
     */
    public function reply(?int $parentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId ?? AgoraMessage::factory(),
        ]);
    }
}
