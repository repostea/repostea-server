<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KarmaEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

final class KarmaEventFactory extends Factory
{
    protected $model = KarmaEvent::class;

    public function definition(): array
    {
        $start = Carbon::now()->subDays(rand(0, 5))->addDays(rand(0, 10));
        $end = (clone $start)->addHours(rand(1, 48));

        $type = $this->faker->randomElement(['boost', 'challenge', 'weekend', 'holiday']);

        return [
            'name' => ucfirst($type) . ' Event',
            'type' => $type,
            'start_at' => $start,
            'end_at' => $end,
            'multiplier' => $this->faker->randomElement([1.5, 2.0, 2.5, 3.0]),
            'description' => $this->faker->sentence(),
            'is_active' => $this->faker->boolean(80),
        ];
    }

    public function active()
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Active Event',
            'is_active' => true,
            'start_at' => now()->subHours(1),
            'end_at' => now()->addDays(2),
        ]);
    }

    public function upcoming()
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Upcoming Event',
            'is_active' => true,
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(3),
        ]);
    }
}
