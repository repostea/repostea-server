<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'code' => Invitation::generateCode(),
            'created_by' => User::factory(),
            'max_uses' => 1,
            'current_uses' => 0,
            'is_active' => true,
            'expires_at' => now()->addDays(30),
        ];
    }

    /**
     * Invitation that has been used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_uses' => $attributes['max_uses'],
            'is_active' => false,
            'used_by' => User::factory(),
            'used_at' => now(),
        ]);
    }

    /**
     * Invitation that is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(1),
        ]);
    }

    /**
     * Invitation that is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Invitation with multiple uses.
     */
    public function multiUse(int $maxUses = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'max_uses' => $maxUses,
        ]);
    }
}
