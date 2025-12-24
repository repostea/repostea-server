<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KarmaLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

final class KarmaLevelFactory extends Factory
{
    protected $model = KarmaLevel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['Novice', 'Apprentice', 'Contributor', 'Expert', 'Mentor', 'Sage', 'Legend']),
            'required_karma' => $this->faker->numberBetween(0, 10000),
            'badge' => $this->faker->imageUrl(50, 50, 'badge'),
            'description' => $this->faker->sentence(10),
            'benefits' => $this->faker->randomElements(['Access to exclusive communities', 'Karma multiplier x1.5', 'Ability to highlight posts', 'Special profile badge'], 2),
        ];
    }
}
