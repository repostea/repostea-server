<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sub;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class SubFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Sub::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::slug($name),
            'display_name' => ucwords($name),
            'description' => fake()->paragraph(),
            'rules' => fake()->optional()->paragraph(),
            'icon' => null,
            'color' => fake()->hexColor(),
            'members_count' => 0,
            'posts_count' => 0,
            'is_private' => false,
            'is_adult' => false,
            'is_featured' => false,
            'require_approval' => false,
            'hide_owner' => false,
            'hide_moderators' => false,
            'allowed_content_types' => null,
            'visibility' => 'public',
            'created_by' => User::factory(),
        ];
    }

    /**
     * Create a private sub.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    /**
     * Create an adult sub.
     */
    public function adult(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_adult' => true,
        ]);
    }

    /**
     * Create a featured sub.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
