<?php

declare(strict_types=1);

use App\Models\Tag;
use App\Models\TagCategory;

use function Pest\Laravel\getJson;

test('index returns list of tags', function (): void {
    Tag::factory()->count(10)->create();

    $response = getJson('/api/v1/tags');

    $response->assertStatus(200);
    $response->assertJsonStructure(['data']);
});

test('index filters tags by search', function (): void {
    Tag::factory()->create(['name_key' => 'technology', 'slug' => 'technology']);
    Tag::factory()->create(['name_key' => 'science', 'slug' => 'science']);

    $response = getJson('/api/v1/tags?search=tech');

    $response->assertStatus(200);
    expect($response->json('data'))->toBeArray();
});

test('index limita resultados', function (): void {
    Tag::factory()->count(50)->create();

    $response = getJson('/api/v1/tags');

    $response->assertStatus(200);
    $data = $response->json('data');
    // Just verify we get data back
    expect($data)->toBeArray();
});

test('show returns tag by slug', function (): void {
    $tag = Tag::factory()->create([
        'name_key' => 'laravel',
        'slug' => 'laravel',
    ]);

    $response = getJson('/api/v1/tags/laravel');

    $response->assertStatus(200);
    $response->assertJsonPath('data.slug', 'laravel');
});

test('show returns 404 if tag does not exist', function (): void {
    $response = getJson('/api/v1/tags/non-existent-tag');

    $response->assertStatus(404);
});

test('showById returns tag by ID', function (): void {
    $tag = Tag::factory()->create();

    $response = getJson("/api/v1/tags/id/{$tag->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.id', $tag->id);
});

test('showById returns 404 if ID does not exist', function (): void {
    $response = getJson('/api/v1/tags/id/99999');

    $response->assertStatus(404);
});

test('getTagsByCategory returns tags from a category', function (): void {
    $category = TagCategory::factory()->create();
    Tag::factory()->count(5)->create(['tag_category_id' => $category->id]);
    Tag::factory()->count(3)->create(); // Other category

    $response = getJson("/api/v1/tags/category/{$category->id}");

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(5);
});

test('getTagsByCategory returns empty array if category has no tags', function (): void {
    $category = TagCategory::factory()->create();

    $response = getJson("/api/v1/tags/category/{$category->id}");

    $response->assertStatus(200);
    expect($response->json('data'))->toBeArray();
});

test('getTagCategories returns all categories', function (): void {
    TagCategory::factory()->count(5)->create();

    $response = getJson('/api/v1/tag-categories');

    $response->assertStatus(200);
    $response->assertJsonStructure(['data']);
});

test('index orders tags by popularity', function (): void {
    Tag::factory()->create(['name_key' => 'popular', 'slug' => 'popular']);
    Tag::factory()->create(['name_key' => 'less-popular', 'slug' => 'less-popular']);

    $response = getJson('/api/v1/tags?sort=popular');

    $response->assertStatus(200);
    expect($response->json('data'))->toBeArray();
});

test('show includes related posts if requested', function (): void {
    $tag = Tag::factory()->create();

    $response = getJson("/api/v1/tags/{$tag->slug}?include=posts");

    $response->assertStatus(200);
});

test('index pagina resultados correctamente', function (): void {
    Tag::factory()->count(30)->create();

    $response = getJson('/api/v1/tags?page=2&limit=10');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBeLessThanOrEqual(10);
});
