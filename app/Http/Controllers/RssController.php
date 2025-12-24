<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use const PATHINFO_EXTENSION;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use League\CommonMark\CommonMarkConverter;
use SimpleXMLElement;

final class RssController extends Controller
{
    /**
     * Generate RSS feed for published posts.
     */
    public function published(Request $request)
    {
        $lang = $request->query('lang');
        $cacheKey = 'rss_published_' . ($lang ?? 'all');

        return Cache::tags(['posts', 'rss'])->remember($cacheKey, 300, function () use ($lang) {
            $query = Post::with('user', 'tags')
                ->where('status', 'published')
                ->whereNotNull('frontpage_at')
                ->orderBy('frontpage_at', 'desc');

            if ($lang) {
                $query->where('language_code', $lang);
            }

            $title = config('site.name') . ' - Published Posts';
            $description = 'News and content aggregator. Create and share original content with the community.';

            if ($lang) {
                $title .= ' (' . strtoupper($lang) . ')';
            }

            $posts = $query->limit(50)->get();

            return $this->generatePostsFeed($posts, $title, $description, $lang, true);
        });
    }

    /**
     * Generate RSS feed for queued posts.
     */
    public function queued(Request $request)
    {
        $lang = $request->query('lang');
        $cacheKey = 'rss_queued_' . ($lang ?? 'all');

        return Cache::tags(['posts', 'rss'])->remember($cacheKey, 300, function () use ($lang) {
            $query = Post::with('user', 'tags')
                ->where('status', 'published')
                ->whereNull('frontpage_at')
                ->orderBy('created_at', 'desc');

            if ($lang) {
                $query->where('language_code', $lang);
            }

            $title = config('site.name') . ' - Queued Posts';
            $description = 'Posts pending to reach the frontpage';

            if ($lang) {
                $title .= ' (' . strtoupper($lang) . ')';
            }

            $posts = $query->limit(50)->get();

            return $this->generatePostsFeed($posts, $title, $description, $lang, false);
        });
    }

    /**
     * Generate XML for posts feed.
     *
     * @param  null|mixed  $lang
     * @param  bool  $includeComments  Whether to include comments link (false for queued posts)
     */
    private function generatePostsFeed($posts, $title, $description, $lang = null, $includeComments = true)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/"></rss>');

        $channel = $xml->addChild('channel');
        $channel->addChild('title', htmlspecialchars($title));
        $channel->addChild('link', config('app.client_url'));
        $channel->addChild('description', htmlspecialchars($description));
        $channel->addChild('language', $lang ?? 'es');

        if ($posts->isNotEmpty()) {
            $channel->addChild('pubDate', $posts->first()->created_at->toRfc2822String());
        }

        // Add atom:link for self-reference
        $atomLink = $channel->addChild('atom:link', '', 'http://www.w3.org/2005/Atom');
        $atomLink->addAttribute('href', url()->current());
        $atomLink->addAttribute('rel', 'self');
        $atomLink->addAttribute('type', 'application/rss+xml');

        foreach ($posts as $post) {
            $item = $channel->addChild('item');
            $item->addChild('title', htmlspecialchars($post->title));

            // Link to the post (frontend URL)
            $postUrl = config('app.client_url') . '/posts/' . $post->slug;
            $item->addChild('link', htmlspecialchars($postUrl));

            // Unique identifier
            $item->addChild('guid', htmlspecialchars($postUrl));

            // Publication date
            $item->addChild('pubDate', $post->created_at->toRfc2822String());

            // Author (only if not anonymous)
            if ($post->user && ! $post->is_anonymous) {
                $item->addChild('author', htmlspecialchars($post->user->username));
            }

            // Description/Content - Strip HTML/Markdown for summary
            $description = '';
            if ($post->content) {
                // Convert Markdown to HTML first
                $converter = new CommonMarkConverter();
                $html = $converter->convert($post->content)->getContent();
                // Remove HTML tags to get plain text
                $plainText = strip_tags($html);
                // Remove multiple spaces and line breaks
                $plainText = preg_replace('/\s+/', ' ', $plainText);
                // Trim and get first 500 characters
                $plainText = trim($plainText);
                $description = substr($plainText, 0, 500);
                if (strlen($plainText) > 500) {
                    $description .= '...';
                }
                $description = htmlspecialchars($description);
            } elseif ($post->url) {
                $description = htmlspecialchars($post->url);
            }

            if ($description) {
                $item->addChild('description', $description);
            }

            // Full content (for RSS readers that support it)
            if ($post->content) {
                // Convert Markdown to HTML for content:encoded
                $converter = new CommonMarkConverter();
                $htmlContent = $converter->convert($post->content)->getContent();

                $contentEncoded = $item->addChild('content:encoded', null, 'http://purl.org/rss/1.0/modules/content/');
                $dom = dom_import_simplexml($contentEncoded);
                $dom->appendChild($dom->ownerDocument->createCDATASection($htmlContent));
            }

            // Categories (tags)
            foreach ($post->tags as $tag) {
                if ($tag->name) {
                    $item->addChild('category', htmlspecialchars($tag->name));
                }
            }

            // Comments link (only for published posts on frontpage)
            if ($includeComments) {
                $item->addChild('comments', htmlspecialchars($postUrl . '#comments'));
            }

            // Image enclosure (for RSS readers to display images)
            if ($post->thumbnail_url) {
                $imageUrl = $post->thumbnail_url;

                // Ensure absolute URL
                if (! str_starts_with($imageUrl, 'http')) {
                    $imageUrl = config('app.url') . $imageUrl;
                }

                // Detect MIME type from file extension
                $extension = strtolower(pathinfo($imageUrl, PATHINFO_EXTENSION));
                $mimeType = match ($extension) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    'svg' => 'image/svg+xml',
                    default => 'image/jpeg',
                };

                $enclosure = $item->addChild('enclosure');
                $enclosure->addAttribute('url', htmlspecialchars($imageUrl));
                $enclosure->addAttribute('type', $mimeType);
                // Length is optional but recommended - we'll skip it for now as we'd need to fetch the file
            }
        }

        return response($xml->asXML(), 200)
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
