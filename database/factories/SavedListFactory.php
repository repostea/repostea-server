<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SavedList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class SavedListFactory extends Factory
{
    protected $model = SavedList::class;

    public function definition()
    {
        $name = $this->faker->words(3, true);

        return [
            'uuid' => Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->boolean(70) ? $this->faker->sentence() : null,
            'user_id' => User::factory(),
            'is_public' => $this->faker->boolean(20),
            'type' => 'custom',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function favorite()
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Favorites',
            'slug' => 'favorites',
            'type' => 'favorite',
            'is_public' => false,
        ]);
    }

    public function readLater()
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Read Later',
            'slug' => 'read-later',
            'type' => 'read_later',
            'is_public' => false,
        ]);
    }
}
