<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();
        $displayName = ucfirst($name);

        return [
            'name' => $displayName,
            'display_name' => $displayName,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
        ];
    }
}
