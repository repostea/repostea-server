<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserStreak;
use Illuminate\Database\Eloquent\Factories\Factory;

final class UserStreakFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = UserStreak::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currentStreak = fake()->numberBetween(1, 30);
        $longestStreak = max($currentStreak, fake()->numberBetween($currentStreak, 60));

        return [
            'user_id' => User::factory(),
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'last_activity_date' => now(),
        ];
    }

    /**
     * State para rachas iniciales (1-7 días).
     */
    public function beginner(): static
    {
        return $this->state(static function (array $attributes) {
            $currentStreak = fake()->numberBetween(1, 7);

            return [
                'current_streak' => $currentStreak,
                'longest_streak' => $currentStreak,
            ];
        });
    }

    /**
     * State para rachas medias (8-30 días).
     */
    public function intermediate(): static
    {
        return $this->state(static function (array $attributes) {
            $currentStreak = fake()->numberBetween(8, 30);
            $longestStreak = max($currentStreak, fake()->numberBetween($currentStreak, 45));

            return [
                'current_streak' => $currentStreak,
                'longest_streak' => $longestStreak,
            ];
        });
    }

    /**
     * State para rachas largas (más de 30 días).
     */
    public function advanced(): static
    {
        return $this->state(static function (array $attributes) {
            $currentStreak = fake()->numberBetween(31, 100);
            $longestStreak = max($currentStreak, fake()->numberBetween($currentStreak, 150));

            return [
                'current_streak' => $currentStreak,
                'longest_streak' => $longestStreak,
            ];
        });
    }

    /**
     * State para rachas muy largas (más de 100 días).
     */
    public function expert(): static
    {
        return $this->state(static function (array $attributes) {
            $currentStreak = fake()->numberBetween(101, 365);
            $longestStreak = $currentStreak;

            return [
                'current_streak' => $currentStreak,
                'longest_streak' => $longestStreak,
            ];
        });
    }
}
