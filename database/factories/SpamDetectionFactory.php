<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SpamDetection>
 */
final class SpamDetectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'content_type' => $this->faker->randomElement(['post', 'comment']),
            'content_id' => $this->faker->numberBetween(1, 1000),
            'detection_type' => $this->faker->randomElement(['duplicate', 'rapid_fire', 'high_spam_score']),
            'similarity' => $this->faker->optional(0.5)->randomFloat(2, 0.8, 1.0),
            'spam_score' => $this->faker->optional(0.5)->numberBetween(70, 100),
            'risk_level' => $this->faker->optional(0.3)->randomElement(['low', 'medium', 'high']),
            'reasons' => $this->faker->optional(0.3)->randomElements(['Short content', 'Excessive caps', 'URL spam'], $this->faker->numberBetween(1, 3)),
            'metadata' => $this->faker->optional(0.3)->passthrough(['duplicate_of_id' => $this->faker->numberBetween(1, 100)]),
            'reviewed' => $this->faker->boolean(30),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'action_taken' => null,
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function reviewed(): self
    {
        return $this->state(fn (array $attributes) => [
            'reviewed' => true,
            'reviewed_by' => User::factory(),
            'reviewed_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'action_taken' => $this->faker->randomElement(['ignored', 'deleted', 'banned', 'warning']),
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'reviewed' => false,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'action_taken' => null,
        ]);
    }

    public function duplicate(): self
    {
        return $this->state(fn (array $attributes) => [
            'detection_type' => 'duplicate',
            'similarity' => $this->faker->randomFloat(2, 0.85, 1.0),
            'metadata' => ['duplicate_of_id' => $this->faker->numberBetween(1, 100)],
        ]);
    }

    public function rapidFire(): self
    {
        return $this->state(fn (array $attributes) => [
            'detection_type' => 'rapid_fire',
            'metadata' => ['posts_count' => $this->faker->numberBetween(5, 20)],
        ]);
    }

    public function highSpamScore(): self
    {
        return $this->state(fn (array $attributes) => [
            'detection_type' => 'high_spam_score',
            'spam_score' => $this->faker->numberBetween(80, 100),
            'risk_level' => $this->faker->randomElement(['medium', 'high']),
            'reasons' => $this->faker->randomElements(['Short content', 'Excessive caps', 'URL spam', 'Keywords match'], $this->faker->numberBetween(2, 4)),
        ]);
    }
}
