<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KarmaHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class KarmaHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = KarmaHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => $this->faker->numberBetween(-50, 100),
            'source' => $this->faker->randomElement(['post', 'comment', 'vote', 'streak', 'level_up', 'event']),
            'source_id' => $this->faker->numberBetween(1, 100),
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Indicate that the karma history is positive.
     *
     * @return Factory
     */
    public function positive()
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $this->faker->numberBetween(1, 100),
        ]);
    }

    /**
     * Indicate that the karma history is negative.
     *
     * @return Factory
     */
    public function negative()
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $this->faker->numberBetween(-50, -1),
        ]);
    }
}
