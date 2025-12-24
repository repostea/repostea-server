<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class PostRelationshipFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PostRelationship::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $relationshipType = fake()->randomElement(PostRelationship::getRelationshipTypes());

        return [
            'source_post_id' => Post::factory(),
            'target_post_id' => Post::factory(),
            'relationship_type' => $relationshipType,
            'relation_category' => PostRelationship::getCategoryForType($relationshipType),
            'created_by' => User::factory(),
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
            'is_anonymous' => fake()->boolean(10),
            'upvotes_count' => fake()->numberBetween(0, 50),
            'downvotes_count' => fake()->numberBetween(0, 10),
            'score' => 0, // Will be calculated based on votes
        ];
    }

    /**
     * Create a reply relationship.
     */
    public function reply(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship_type' => PostRelationship::TYPE_REPLY,
            'relation_category' => PostRelationship::CATEGORY_EXTERNAL,
        ]);
    }

    /**
     * Create a continuation relationship.
     */
    public function continuation(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship_type' => PostRelationship::TYPE_CONTINUATION,
            'relation_category' => PostRelationship::CATEGORY_OWN,
        ]);
    }

    /**
     * Create a related relationship.
     */
    public function related(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship_type' => PostRelationship::TYPE_RELATED,
            'relation_category' => PostRelationship::CATEGORY_EXTERNAL,
        ]);
    }

    /**
     * Create an update relationship.
     */
    public function update(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship_type' => PostRelationship::TYPE_UPDATE,
            'relation_category' => PostRelationship::CATEGORY_EXTERNAL,
        ]);
    }

    /**
     * Create a correction relationship.
     */
    public function correction(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship_type' => PostRelationship::TYPE_CORRECTION,
            'relation_category' => PostRelationship::CATEGORY_OWN,
        ]);
    }

    /**
     * Create a duplicate relationship.
     */
    public function duplicate(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship_type' => PostRelationship::TYPE_DUPLICATE,
            'relation_category' => PostRelationship::CATEGORY_EXTERNAL,
        ]);
    }

    /**
     * Create an anonymous relationship.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => true,
        ]);
    }
}
