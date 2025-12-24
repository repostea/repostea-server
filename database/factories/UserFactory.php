<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KarmaLevel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $noviceLevel = KarmaLevel::where('required_karma', 0)->first();

        $username = Str::slug(fake()->unique()->userName());

        return [
            'username' => $username,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Str::random(32), // The 'hashed' cast on User model will hash this
            'remember_token' => Str::random(10),
            'karma_points' => fake()->numberBetween(0, 1000),
            'highest_level_id' => $noviceLevel ? $noviceLevel->id : null,
            'locale' => fake()->randomElement(['es', 'en', 'fr', 'de', 'pt']),
            'is_verified_expert' => fake()->boolean(10), // 10% will be verified experts
        ];
    }

    /**
     * State para usuarios administradores.
     */
    public function admin(): static
    {
        return $this->afterCreating(static function (User $user): void {
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole) {
                $user->roles()->attach($adminRole);
            }

            $user->update([
                'karma_points' => fake()->numberBetween(3000, 5000),
            ]);
        });
    }

    /**
     * State para usuarios moderadores.
     */
    public function moderator(): static
    {
        return $this->afterCreating(static function (User $user): void {
            $moderatorRole = Role::where('slug', 'moderator')->first();
            if ($moderatorRole) {
                $user->roles()->attach($moderatorRole);
            }

            $user->update([
                'karma_points' => fake()->numberBetween(1500, 3000),
            ]);
        });
    }

    /**
     * State para usuarios expertos.
     */
    public function expert(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified_expert' => true,
            'expertise_areas' => fake()->randomElement(['Tecnología', 'Ciencia', 'Medicina', 'Derecho', 'Economía']),
            'professional_title' => fake()->jobTitle(),
            'academic_degree' => fake()->randomElement(['Ph.D.', 'M.Sc.', 'MBA', 'Lic.', 'Ing.']),
            'karma_points' => fake()->numberBetween(1000, 3000),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
