<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function it_can_list_all_tags(): void
    {
        $tags = Tag::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/tags');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name_key', 'name', 'slug',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_can_filter_tags_by_category(): void
    {
        $category1 = TagCategory::factory()->create();
        $category2 = TagCategory::factory()->create();

        $tags1 = Tag::factory()->count(3)->create([
            'tag_category_id' => $category1->id,
        ]);

        $tags2 = Tag::factory()->count(2)->create([
            'tag_category_id' => $category2->id,
        ]);

        $response = $this->getJson("/api/v1/tags?category_id={$category1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        $responseTagIds = collect($response->json('data'))->pluck('id')->all();
        $expectedTagIds = $tags1->pluck('id')->all();
        $this->assertEqualsCanonicalizing($expectedTagIds, $responseTagIds);
    }

    #[Test]
    public function it_can_filter_tags_with_no_category(): void
    {
        $tagsNoCategory = Tag::factory()->count(2)->create([
            'tag_category_id' => null,
        ]);

        $categoryWithTags = TagCategory::factory()->create();
        $tagsWithCategory = Tag::factory()->count(3)->create([
            'tag_category_id' => $categoryWithTags->id,
        ]);

        $response = $this->getJson('/api/v1/tags?category_id=null');

        $response->assertStatus(200);

        $responseTagIds = collect($response->json('data'))->pluck('id')->all();
        $expectedTagIds = $tagsNoCategory->pluck('id')->all();
        $this->assertEqualsCanonicalizing($expectedTagIds, $responseTagIds);
    }

    #[Test]
    public function it_can_filter_tags_by_content_type(): void
    {
        $tags = Tag::factory()->count(5)->create();

        $post1 = Post::factory()->create(['content_type' => 'text']);
        $post2 = Post::factory()->create(['content_type' => 'link']);

        $post1->tags()->attach([$tags[0]->id, $tags[1]->id]);
        $post2->tags()->attach([$tags[2]->id, $tags[3]->id]);

        $response = $this->getJson('/api/v1/tags?content_type=text');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $responseTagIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $expectedTagIds = [$tags[0]->id, $tags[1]->id];
        sort($expectedTagIds);
        $this->assertEquals($expectedTagIds, $responseTagIds);
    }

    #[Test]
    public function it_can_show_a_specific_tag_by_slug(): void
    {
        $tag = Tag::factory()->create([
            'name_key' => 'test_tag',
            'slug' => 'test-tag',
        ]);

        $response = $this->getJson("/api/v1/tags/{$tag->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $tag->id,
                    'name_key' => 'test_tag',
                    'slug' => 'test-tag',
                ],
            ]);
    }

    #[Test]
    public function it_can_show_a_specific_tag_by_id(): void
    {
        $tag = Tag::factory()->create([
            'name_key' => 'test_tag',
            'slug' => 'test-tag',
        ]);

        $response = $this->getJson("/api/v1/tags/id/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $tag->id,
                    'name_key' => 'test_tag',
                    'slug' => 'test-tag',
                ],
            ]);
    }

    #[Test]
    public function it_can_get_tags_by_category(): void
    {
        $category = TagCategory::factory()->create();

        $tags = Tag::factory()->count(3)->create([
            'tag_category_id' => $category->id,
        ]);

        $response = $this->getJson("/api/v1/tags/category/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name_key', 'name', 'slug', 'category_id',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_can_get_all_tag_categories_with_tags(): void
    {
        Cache::flush();

        $categories = TagCategory::factory()->count(2)->create();

        foreach ($categories as $category) {
            $tags = Tag::factory()->count(3)->create([
                'tag_category_id' => $category->id,
            ]);
        }

        $response = $this->getJson('/api/v1/tag-categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name_key', 'name', 'slug', 'icon', 'tags',
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        $categoryIds = collect($categories)->pluck('id')->toArray();

        foreach ($categoryIds as $categoryId) {
            $this->assertNotEmpty(
                collect($responseData)->where('id', $categoryId)->first(),
            );
        }
    }
}
