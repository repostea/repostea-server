<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class AchievementFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Achievement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(fake()->numberBetween(2, 4), true);
        $name = ucfirst($name);

        $types = ['registration', 'action', 'streak', 'karma', 'post', 'comment', 'vote'];
        $type = fake()->randomElement($types);

        $icons = [
            'fas fa-trophy', 'fas fa-medal', 'fas fa-award', 'fas fa-star',
            'fas fa-certificate', 'fas fa-crown', 'fas fa-gem', 'fas fa-heart',
            'fas fa-thumbs-up', 'fas fa-comment', 'fas fa-calendar-check',
        ];

        $requirements = [];

        switch ($type) {
            case 'registration':
                $requirements = ['action' => 'register'];
                break;

            case 'action':
                $actionType = fake()->randomElement(['post', 'comment', 'vote', 'login']);
                $count = fake()->randomElement([1, 5, 10, 25, 50, 100]);
                $requirements = ['action' => $actionType, 'count' => $count];
                break;

            case 'streak':
                $days = fake()->randomElement([7, 30, 90, 180, 365]);
                $requirements = ['streak' => $days];
                break;

            case 'karma':
                $points = fake()->randomElement([100, 500, 1000, 2500, 5000]);
                $requirements = ['karma' => $points];
                break;

            case 'post':
                $count = fake()->randomElement([1, 5, 10, 25, 50]);
                $requirements = ['post_count' => $count];
                break;

            case 'comment':
                $count = fake()->randomElement([1, 10, 50, 100, 250]);
                $requirements = ['comment_count' => $count];
                break;

            case 'vote':
                $count = fake()->randomElement([10, 50, 100, 500, 1000]);
                $requirements = ['vote_count' => $count];
                break;
        }

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(fake()->numberBetween(10, 20)),
            'icon' => fake()->randomElement($icons),
            'type' => $type,
            'requirements' => $requirements,
            'karma_bonus' => fake()->randomElement([0, 5, 10, 20, 50, 100, 500]),
        ];
    }

    /**
     * State para logros de racha.
     */
    public function streak(): static
    {
        return $this->state(static function (array $attributes) {
            $days = fake()->randomElement([7, 30, 90, 180, 365]);
            $name = "Racha de {$days} días";

            return [
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "Has estado activo por {$days} días consecutivos.",
                'icon' => 'fas fa-calendar-check',
                'type' => 'streak',
                'requirements' => ['streak' => $days],
                'karma_bonus' => $days <= 30 ? 25 : ($days <= 90 ? 75 : ($days <= 180 ? 200 : 500)),
            ];
        });
    }

    /**
     * State para logros de karma.
     */
    public function karmaPoints(): static
    {
        return $this->state(static function (array $attributes) {
            $points = fake()->randomElement([100, 500, 1000, 2500, 5000]);
            $name = "Karma {$points}";

            return [
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "Alcanzaste {$points} puntos de karma.",
                'icon' => 'fas fa-star',
                'type' => 'karma',
                'requirements' => ['karma' => $points],
                'karma_bonus' => 0, // No karma bonus for karma achievements to avoid loops
            ];
        });
    }

    /**
     * State para logros de acciones.
     */
    public function action(): static
    {
        return $this->state(static function (array $attributes) {
            $actionType = fake()->randomElement(['post', 'comment', 'vote']);
            $count = fake()->randomElement([1, 5, 10, 25, 50, 100]);

            $actionNames = [
                'post' => 'Publicación',
                'comment' => 'Comentario',
                'vote' => 'Voto',
            ];

            $name = $count === 1
                ? "Primer {$actionNames[$actionType]}"
                : "{$count} {$actionNames[$actionType]}s";

            $articulo = '';
            if ($count === 1) {
                if ($actionType === 'post') {
                    $articulo = 'a';
                }
            }

            if ($count === 1) {
                $description = "Creaste tu primer{$articulo} {$actionNames[$actionType]}.";
            } else {
                $description = "Has creado {$count} {$actionNames[$actionType]}s.";
            }

            $icons = [
                'post' => 'fas fa-pencil-alt',
                'comment' => 'fas fa-comment',
                'vote' => 'fas fa-thumbs-up',
            ];

            return [
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $description,
                'icon' => $icons[$actionType],
                'type' => 'action',
                'requirements' => ['action' => $actionType, 'count' => $count],
                'karma_bonus' => min($count * 2, 100), // Maximum 100 karma bonus
            ];
        });
    }
}
