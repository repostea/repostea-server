<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TagCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class TagCategoryFactory extends Factory
{
    protected $model = TagCategory::class;

    public function definition()
    {
        $name = $this->faker->unique()->word();

        return [
            'name_key' => Str::snake($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->boolean(70) ? $this->faker->sentence() : null,
            'icon' => $this->faker->randomElement(['📝', '💡', '🔥', '⭐', '🎯', '📊']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
