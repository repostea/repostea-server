<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\getJson;

test('getTwitterMetadata requires url', function (): void {
    $response = getJson('/api/v1/media/twitter-metadata');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['url']);
});

test('getTwitterMetadata validates url format', function (): void {
    $response = getJson('/api/v1/media/twitter-metadata?url=not-a-valid-url');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['url']);
});

test('getTwitterMetadata accepts valid urls', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test User',
            'author_url' => 'https://twitter.com/testuser',
            'provider_name' => 'Twitter',
            'html' => '<blockquote><p>Test tweet</p></blockquote>',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'data' => [
            'thumbnail_url',
            'author_name',
            'author_url',
            'provider_name',
            'tweet_text',
        ],
    ]);
});

test('getTwitterMetadata returns success true when data exists', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test User',
            'author_url' => 'https://twitter.com/testuser',
            'provider_name' => 'Twitter',
            'html' => '<blockquote><p>Test tweet content</p></blockquote>',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
});

test('getTwitterMetadata extrae author_name correctamente', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'John Doe',
            'author_url' => 'https://twitter.com/johndoe',
            'provider_name' => 'Twitter',
            'html' => '<blockquote><p>Tweet</p></blockquote>',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $response->assertJsonPath('data.author_name', 'John Doe');
});

test('getTwitterMetadata extrae author_url correctamente', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'John Doe',
            'author_url' => 'https://twitter.com/johndoe',
            'provider_name' => 'Twitter',
            'html' => '<blockquote><p>Tweet</p></blockquote>',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $response->assertJsonPath('data.author_url', 'https://twitter.com/johndoe');
});

test('getTwitterMetadata uses provider_name from response', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test',
            'provider_name' => 'X',
            'html' => '<blockquote><p>Tweet</p></blockquote>',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $response->assertJsonPath('data.provider_name', 'X');
});

test('getTwitterMetadata uses Twitter as default provider_name', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test',
            'html' => '<blockquote><p>Tweet</p></blockquote>',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $response->assertJsonPath('data.provider_name', 'Twitter');
});

test('getTwitterMetadata extracts thumbnail from html', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test',
            'html' => '<blockquote><img src="https://example.com/thumb.jpg" /><p>Tweet</p></blockquote>',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $response->assertJsonPath('data.thumbnail_url', 'https://example.com/thumb.jpg');
});

test('getTwitterMetadata extracts tweet text', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test',
            'html' => '<blockquote class="twitter-tweet"><p lang="en" dir="ltr">This is a test tweet</p></blockquote>',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $response->assertJsonPath('data.tweet_text', 'This is a test tweet');
});

test('getTwitterMetadata trunca textos largos a 150 caracteres', function (): void {
    $longText = str_repeat('A', 200);
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test',
            'html' => "<blockquote class=\"twitter-tweet\"><p lang=\"en\" dir=\"ltr\">{$longText}</p></blockquote>",
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $tweetText = $response->json('data.tweet_text');
    expect(mb_strlen($tweetText))->toBeLessThanOrEqual(150);
    expect($tweetText)->toEndWith('...');
});

test('getTwitterMetadata cleans text whitespace', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test',
            'html' => "<blockquote class=\"twitter-tweet\"><p lang=\"en\" dir=\"ltr\">Text   with\n\nmultiple    spaces</p></blockquote>",
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $tweetText = $response->json('data.tweet_text');
    expect($tweetText)->toBe('Text with multiple spaces');
});

test('getTwitterMetadata handles response without html', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test',
            'author_url' => 'https://twitter.com/test',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $response->assertJsonPath('data.thumbnail_url', null);
    $response->assertJsonPath('data.tweet_text', null);
});

test('getTwitterMetadata returns error when API fails', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([], 404),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(400);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Could not fetch Twitter metadata');
});

test('getTwitterMetadata cachea resultados', function (): void {
    Cache::flush();

    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test User',
            'html' => '<blockquote><p>Test tweet</p></blockquote>',
        ], 200),
    ]);

    $url = 'https://twitter.com/user/status/123';

    // First request
    $response1 = getJson("/api/v1/media/twitter-metadata?url={$url}");
    $response1->assertStatus(200);

    // Second request should use cache
    $response2 = getJson("/api/v1/media/twitter-metadata?url={$url}");
    $response2->assertStatus(200);

    // Should only have made one HTTP request
    Http::assertSentCount(1);
});

test('getTwitterMetadata does not require authentication', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test',
            'html' => '<blockquote><p>Tweet</p></blockquote>',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
});

test('getTwitterMetadata handles HTML entities correctly', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'author_name' => 'Test',
            'html' => '<blockquote class="twitter-tweet"><p lang="en" dir="ltr">Test &amp; tweet &lt;3 &quot;quotes&quot;</p></blockquote>',
        ], 200),
    ]);

    $response = getJson('/api/v1/media/twitter-metadata?url=https://twitter.com/user/status/123');

    $response->assertStatus(200);
    $tweetText = $response->json('data.tweet_text');
    expect($tweetText)->toBe('Test & tweet <3 "quotes"');
});
