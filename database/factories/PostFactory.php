<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contentType = fake()->randomElement(['link', 'text']);
        $isOriginal = $contentType === 'text' ? fake()->boolean(80) : fake()->boolean(20);

        return [
            'title' => fake()->sentence(rand(5, 10)),
            'content' => $contentType === 'text' ? fake()->paragraphs(rand(3, 7), true) : fake()->paragraph(),
            'url' => $contentType === 'link' ? fake()->url() : null,
            'thumbnail_url' => fake()->boolean(70) ? $this->imageUrl(480, 480) : null,
            'user_id' => User::factory(),
            'is_original' => $isOriginal,
            'status' => 'published',
            'votes_count' => 0,
            'comment_count' => 0,
            'views' => fake()->numberBetween(10, 1000),
            'source' => $isOriginal ? null : fake()->company(),
            'language_code' => fake()->randomElement(['es', 'en', 'fr', 'de', 'pt']),
            'is_external_import' => false,
            'external_id' => null,
            'source_name' => null,
            'source_url' => null,
            'original_published_at' => null,
            'content_type' => $contentType,
            'media_metadata' => null,
            'media_url' => null,
            'slug' => null,
            'uuid' => fake()->uuid(),
        ];
    }

    protected function imageUrl(int $width = 480, int $height = 480): string
    {

        $randomId = rand(1, 1000);

        return "https://picsum.photos/id/{$randomId}/{$width}/{$height}";
    }

    public function article(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => 'text',
            'url' => null,
            'content' => fake()->paragraphs(rand(3, 7), true),
            'is_original' => true,
        ]);
    }

    public function link(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => 'link',
            'url' => fake()->url(),
            'content' => fake()->paragraph(),
            'is_original' => false,
        ]);
    }

    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'votes_count' => fake()->numberBetween(50, 500),
            'comment_count' => fake()->numberBetween(20, 100),
            'views' => fake()->numberBetween(500, 5000),
        ]);
    }

    public function importedFromExternal(): static
    {
        $externalSources = [
            'lobsters' => ['name' => 'Lobsters', 'domain' => 'lobste.rs'],
            'hackernews' => ['name' => 'Hacker News', 'domain' => 'news.ycombinator.com'],
            'reddit' => ['name' => 'Reddit', 'domain' => 'reddit.com'],
            'mediatize' => ['name' => 'Mediatize', 'domain' => 'mediatize.info'],
        ];
        $sourceName = array_rand($externalSources);
        $source = $externalSources[$sourceName];

        $sourceUrl = 'https://www.' . $source['domain'] . '/item/' . fake()->randomNumber(6);

        return $this->state(fn (array $attributes) => [
            'content_type' => 'link',
            'url' => fake()->url(),
            'content' => fake()->paragraph(),
            'is_original' => false,
            'source' => $sourceName,
            'source_name' => $source['name'],
            'source_url' => $sourceUrl,
            'external_source' => $source['name'],
        ]);
    }
}
