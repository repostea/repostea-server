<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition()
    {
        $name = $this->faker->unique()->word();

        return [
            'name_key' => Str::snake($name),
            'slug' => Str::slug($name),
            'description_key' => $this->faker->boolean(30) ? Str::snake($this->faker->words(3, true)) : null,
            'tag_category_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function withCategory()
    {
        return $this->state(fn (array $attributes) => [
            'tag_category_id' => TagCategory::factory(),
        ]);
    }
}
