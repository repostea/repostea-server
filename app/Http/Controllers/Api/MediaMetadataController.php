<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use const ENT_HTML5;
use const ENT_QUOTES;
use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Services\UrlValidationService;
use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class MediaMetadataController extends Controller
{
    public function __construct(
        private readonly UrlValidationService $urlValidator,
    ) {}

    public function getTwitterMetadata(Request $request): mixed
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $url = $request->input('url');

        $cacheKey = 'twitter_metadata_' . md5($url);

        return Cache::tags(['media'])->remember($cacheKey, 3600, function () use ($url) {
            try {
                $response = Http::timeout(10)->get('https://publish.twitter.com/oembed', [
                    'url' => $url,
                    'omit_script' => true,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    $thumbnailUrl = null;
                    $tweetText = null;

                    if (isset($data['html'])) {
                        preg_match('/<img[^>]+src="([^">]+)"/', $data['html'], $imgMatches);
                        if (isset($imgMatches[1])) {
                            $thumbnailUrl = $imgMatches[1];
                        }

                        preg_match('/<p\s+[^>]*>(.*?)<\/p>/s', $data['html'], $textMatches);
                        if (isset($textMatches[1])) {
                            $tweetText = strip_tags($textMatches[1]);
                            $tweetText = html_entity_decode($tweetText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $tweetText = preg_replace('/\s+/', ' ', trim($tweetText));
                            if (mb_strlen($tweetText) > 150) {
                                $tweetText = mb_substr($tweetText, 0, 147) . '...';
                            }
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'thumbnail_url' => $thumbnailUrl,
                            'author_name' => $data['author_name'] ?? null,
                            'author_url' => $data['author_url'] ?? null,
                            'provider_name' => $data['provider_name'] ?? 'Twitter',
                            'tweet_text' => $tweetText,
                        ],
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Could not fetch Twitter metadata',
                ], 400);

            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => ErrorHelper::getSafeMessage($e, __('messages.media.twitter_fetch_error')),
                ], 500);
            }
        });
    }

    /**
     * Extract OpenGraph and meta information from any URL.
     */
    public function getUrlMetadata(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $url = $request->input('url');

        try {
            $this->urlValidator->validate($url);
        } catch (InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'message' => __('messages.url_validation.not_allowed'),
            ], 400);
        }

        $cacheKey = 'url_metadata_' . md5($url);

        return Cache::tags(['media'])->remember($cacheKey, 3600, function () use ($url): JsonResponse {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                    ])
                    ->get($url);

                if (! $response->successful()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Could not fetch URL',
                    ], 400);
                }

                $html = $response->body();
                $metadata = $this->extractMetadata($html, $url);

                return response()->json([
                    'success' => true,
                    'data' => $metadata,
                ]);

            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => ErrorHelper::getSafeMessage($e, __('messages.media.url_fetch_error')),
                ], 500);
            }
        });
    }

    /**
     * Extract metadata from HTML content.
     *
     * @return array{title: string|null, description: string|null, image: string|null, site_name: string|null, type: string|null, author: string|null, published_date: string|null, canonical_url: string|null}
     */
    private function extractMetadata(string $html, string $originalUrl): array
    {
        $metadata = [
            'title' => null,
            'description' => null,
            'image' => null,
            'site_name' => null,
            'type' => null,
            'author' => null,
            'published_date' => null,
            'canonical_url' => null,
        ];

        // Suppress DOM parsing warnings
        libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);

        $xpath = new DOMXPath($doc);

        // Extract OpenGraph metadata (priority)
        $ogTags = [
            'og:title' => 'title',
            'og:description' => 'description',
            'og:image' => 'image',
            'og:site_name' => 'site_name',
            'og:type' => 'type',
            'og:url' => 'canonical_url',
            'article:author' => 'author',
            'article:published_time' => 'published_date',
        ];

        foreach ($ogTags as $property => $key) {
            $nodes = $xpath->query("//meta[@property='{$property}']/@content");
            if ($nodes !== false && $nodes->length > 0) {
                $metadata[$key] = trim($nodes->item(0)->nodeValue ?? '');
            }
        }

        // Fallback to Twitter Card metadata
        $twitterTags = [
            'twitter:title' => 'title',
            'twitter:description' => 'description',
            'twitter:image' => 'image',
            'twitter:creator' => 'author',
        ];

        foreach ($twitterTags as $name => $key) {
            if (empty($metadata[$key])) {
                $nodes = $xpath->query("//meta[@name='{$name}']/@content");
                if ($nodes !== false && $nodes->length > 0) {
                    $metadata[$key] = trim($nodes->item(0)->nodeValue ?? '');
                }
            }
        }

        // Fallback to standard meta tags
        $standardTags = [
            'description' => 'description',
            'author' => 'author',
        ];

        foreach ($standardTags as $name => $key) {
            if (empty($metadata[$key])) {
                $nodes = $xpath->query("//meta[@name='{$name}']/@content");
                if ($nodes !== false && $nodes->length > 0) {
                    $metadata[$key] = trim($nodes->item(0)->nodeValue ?? '');
                }
            }
        }

        // Fallback to <title> tag if og:title not found
        if (empty($metadata['title'])) {
            $titleNodes = $xpath->query('//title');
            if ($titleNodes !== false && $titleNodes->length > 0) {
                $metadata['title'] = trim($titleNodes->item(0)->textContent ?? '');
            }
        }

        // Look for canonical URL
        if (empty($metadata['canonical_url'])) {
            $canonicalNodes = $xpath->query("//link[@rel='canonical']/@href");
            if ($canonicalNodes !== false && $canonicalNodes->length > 0) {
                $metadata['canonical_url'] = trim($canonicalNodes->item(0)->nodeValue ?? '');
            }
        }

        // Look for JSON-LD structured data
        $jsonLdNodes = $xpath->query("//script[@type='application/ld+json']");
        if ($jsonLdNodes !== false && $jsonLdNodes->length > 0) {
            foreach ($jsonLdNodes as $node) {
                $jsonContent = $node->textContent;
                $jsonData = json_decode($jsonContent, true);

                if (is_array($jsonData)) {
                    $this->extractFromJsonLd($jsonData, $metadata);
                }
            }
        }

        // Clean and validate the image URL
        if (! empty($metadata['image'])) {
            $metadata['image'] = $this->normalizeUrl($metadata['image'], $originalUrl);
        }

        // Clean up title and description
        if (! empty($metadata['title'])) {
            $metadata['title'] = html_entity_decode($metadata['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $metadata['title'] = preg_replace('/\s+/', ' ', trim($metadata['title']));
            // Limit title length
            if (mb_strlen($metadata['title']) > 255) {
                $metadata['title'] = mb_substr($metadata['title'], 0, 252) . '...';
            }
        }

        if (! empty($metadata['description'])) {
            $metadata['description'] = html_entity_decode($metadata['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $metadata['description'] = preg_replace('/\s+/', ' ', trim($metadata['description']));
            // Limit description length
            if (mb_strlen($metadata['description']) > 500) {
                $metadata['description'] = mb_substr($metadata['description'], 0, 497) . '...';
            }
        }

        libxml_clear_errors();

        return $metadata;
    }

    /**
     * Extract metadata from JSON-LD structured data.
     *
     * @param  array<string, mixed>  $jsonData
     * @param  array<string, mixed>  $metadata
     */
    private function extractFromJsonLd(array $jsonData, array &$metadata): void
    {
        // Handle @graph arrays
        if (isset($jsonData['@graph']) && is_array($jsonData['@graph'])) {
            foreach ($jsonData['@graph'] as $item) {
                if (is_array($item)) {
                    $this->extractFromJsonLd($item, $metadata);
                }
            }

            return;
        }

        // Extract title from headline
        if (empty($metadata['title']) && isset($jsonData['headline'])) {
            $metadata['title'] = (string) $jsonData['headline'];
        }

        // Extract description
        if (empty($metadata['description']) && isset($jsonData['description'])) {
            $metadata['description'] = (string) $jsonData['description'];
        }

        // Extract image
        if (empty($metadata['image'])) {
            if (isset($jsonData['image'])) {
                if (is_string($jsonData['image'])) {
                    $metadata['image'] = $jsonData['image'];
                } elseif (is_array($jsonData['image'])) {
                    if (isset($jsonData['image']['url'])) {
                        $metadata['image'] = (string) $jsonData['image']['url'];
                    } elseif (isset($jsonData['image'][0])) {
                        $firstImage = $jsonData['image'][0];
                        $metadata['image'] = is_string($firstImage) ? $firstImage : ($firstImage['url'] ?? null);
                    }
                }
            }
        }

        // Extract author
        if (empty($metadata['author']) && isset($jsonData['author'])) {
            if (is_string($jsonData['author'])) {
                $metadata['author'] = $jsonData['author'];
            } elseif (is_array($jsonData['author'])) {
                $metadata['author'] = $jsonData['author']['name'] ?? ($jsonData['author'][0]['name'] ?? null);
            }
        }

        // Extract published date
        if (empty($metadata['published_date'])) {
            $dateFields = ['datePublished', 'dateCreated', 'dateModified'];
            foreach ($dateFields as $field) {
                if (isset($jsonData[$field])) {
                    $metadata['published_date'] = (string) $jsonData[$field];
                    break;
                }
            }
        }

        // Extract site name from publisher
        if (empty($metadata['site_name']) && isset($jsonData['publisher'])) {
            if (is_array($jsonData['publisher']) && isset($jsonData['publisher']['name'])) {
                $metadata['site_name'] = (string) $jsonData['publisher']['name'];
            }
        }
    }

    /**
     * Normalize relative URLs to absolute URLs.
     */
    private function normalizeUrl(string $url, string $baseUrl): string
    {
        // Already absolute URL
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        // Protocol-relative URL
        if (str_starts_with($url, '//')) {
            $parsedBase = parse_url($baseUrl);

            return ($parsedBase['scheme'] ?? 'https') . ':' . $url;
        }

        // Relative URL - convert to absolute
        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';

        if (str_starts_with($url, '/')) {
            // Root-relative URL
            return $scheme . '://' . $host . $url;
        }

        // Path-relative URL
        $path = $parsedBase['path'] ?? '/';
        $path = dirname($path);

        return $scheme . '://' . $host . $path . '/' . $url;
    }
}
