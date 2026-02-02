<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

final class PostsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->info('No users available to create posts. Creating an admin user...');

            $admin = User::factory()->create([
                'name' => 'Admin',
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => bcrypt(env('ADMIN_PASSWORD', 'changeme123')),
                'karma_points' => 5000,
                'locale' => 'en',
                'email_verified_at' => now(),
            ]);

            $users = collect([$admin]);
        }

        $this->command->info('Creating sample posts...');
        $examplePosts = $this->getExamplePosts();

        foreach ($examplePosts as $postData) {
            $user = $users->random();

            if (! isset($postData['content_type'])) {
                $postData['content_type'] = ($postData['type'] === 'article') ? 'text' : 'link';
            }

            $post = Post::create(array_merge($postData, [
                'user_id' => $user->id,
                'views' => rand(5, 500),
                'language_code' => 'en',
                'status' => 'published',
                'votes_count' => 0,
                'comment_count' => 0,
            ]));
            $this->command->info("Post created: {$post->title}");
        }

        $users->each(function ($user): void {
            $numPosts = rand(1, 5);
            for ($i = 0; $i < $numPosts; $i++) {
                $type = (rand(1, 100) <= 60) ? 'article' : 'link';
                $isOriginal = ($type === 'article') ? true : false;
                $contentType = ($type === 'article') ? 'text' : 'link';

                $post = Post::create([
                    'title' => $this->generateTitle($type),
                    'content' => $this->generateContent($type),
                    'url' => ($type === 'link') ? $this->generateUrl() : null,
                    'thumbnail_url' => (rand(1, 100) <= 70) ? $this->generateThumbnailUrl() : null,
                    'user_id' => $user->id,
                    'type' => $type,
                    'content_type' => $contentType,
                    'is_original' => $isOriginal,
                    'status' => 'published',
                    'votes_count' => 0,
                    'comment_count' => 0,
                    'views' => rand(5, 1000),
                    'language_code' => 'en',
                ]);

                $this->assignRandomTags($post);

                $this->command->info("Random post created: {$post->title}");
            }
        });
    }

    private function assignRandomTags(Post $post): void
    {
        $tags = Tag::inRandomOrder()->take(rand(1, 3))->get();
        $post->tags()->sync($tags->pluck('id'));
    }

    private function getExamplePosts(): array
    {
        $disclaimer = "[This content is just an example. It will be removed when the site development is complete and we move to production.]\n\n";

        return [
            [
                'title' => 'Welcome to Repostea: The new content platform',
                'content' => $disclaimer . "We are pleased to introduce Repostea, a platform designed to share, discover, and discuss relevant content...\n\nAt Repostea we value quality over quantity...",
                'type' => 'article',
                'is_original' => true,
            ],
            [
                'title' => 'Beginner\'s Guide: How to accumulate karma on Repostea',
                'content' => $disclaimer . "Are you new to Repostea? Learn how our karma system works!\n\n1. **Quality posts**...",
                'type' => 'article',
                'is_original' => true,
            ],
            [
                'title' => 'Artificial intelligence revolutionizes software development',
                'content' => $disclaimer . 'The integration of artificial intelligence in software development is transforming the way we code...',
                'type' => 'article',
                'is_original' => true,
            ],
            [
                'title' => 'James Webb discovers atmosphere on potentially habitable exoplanet',
                'url' => 'https://www.example.com/james-webb-exoplanet',
                'content' => $disclaimer . 'NASA scientists have announced an important discovery thanks to the James Webb Space Telescope.',
                'type' => 'link',
                'is_original' => false,
            ],
            [
                'title' => 'Tutorial: Create your first web application with Laravel 10',
                'url' => 'https://www.example.com/tutorial-laravel',
                'content' => $disclaimer . 'In this tutorial you will learn how to create a complete application using the Laravel 10 framework.',
                'type' => 'link',
                'is_original' => false,
            ],
            [
                'title' => 'The best science fiction books of the last decade',
                'content' => $disclaimer . 'The last decade has been extraordinary for literary science fiction. Here is my personal list of the best books...',
                'type' => 'article',
                'is_original' => true,
            ],
        ];
    }

    /**
     * Generate a random title for a post.
     */
    private function generateTitle(string $type): string
    {
        $articleTitles = [
            'The 10 technology trends that will dominate next year',
            'How to improve your productivity with time management techniques',
            'Analysis: The impact of artificial intelligence on our society',
            'Complete guide to learning programming from scratch',
            'The best travel destinations for 2025',
            'The importance of financial education in times of crisis',
            'Review: The latest bestselling novel everyone is talking about',
            'The sustainable revolution: How to reduce your ecological footprint',
            'The future of work: Trends and predictions',
            'Healthy recipes for a balanced diet',
        ];

        $linkTitles = [
            'Scientists discover a new method to treat cancer',
            'Apple unveils its new revolutionary device',
            'Climate change reaches a tipping point, according to study',
            'New technology regulations will come into effect next month',
            'Historic record on the stock market after economic announcement',
            'Leaked specifications for the next iPhone',
            'NASA announces crewed mission to Mars for 2030',
            'Major archaeological discovery reveals ancient civilization',
            'Study reveals unexpected benefits of moderate exercise',
            'Government approves new data protection law',
        ];

        $titles = ($type === 'article') ? $articleTitles : $linkTitles;

        return $titles[array_rand($titles)];
    }

    /**
     * Generate random content for a post.
     */
    private function generateContent(string $type): string
    {
        if ($type === 'article') {
            $paragraphs = rand(3, 7);
            $content = '';

            for ($i = 0; $i < $paragraphs; $i++) {
                $sentences = rand(3, 8);
                $paragraph = '';

                for ($j = 0; $j < $sentences; $j++) {
                    $wordCount = rand(8, 15);
                    $sentence = ucfirst($this->generateRandomWords($wordCount)) . '. ';
                    $paragraph .= $sentence;
                }

                $content .= $paragraph . "\n\n";
            }

            return trim($content);
        }

        $sentences = rand(1, 3);
        $content = '';

        for ($i = 0; $i < $sentences; $i++) {
            $wordCount = rand(8, 15);
            $sentence = ucfirst($this->generateRandomWords($wordCount)) . '. ';
            $content .= $sentence;
        }

        return trim($content);

    }

    /**
     * Generate random words.
     */
    private function generateRandomWords(int $count): string
    {
        $words = [
            'technology', 'development', 'innovation', 'science', 'research',
            'economy', 'society', 'future', 'analysis', 'study', 'time',
            'programming', 'intelligence', 'artificial', 'data', 'security',
            'application', 'system', 'user', 'design', 'project', 'company',
            'industry', 'change', 'process', 'product', 'service', 'client',
            'market', 'business', 'strategy', 'growth', 'investment', 'success',
            'global', 'digital', 'modern', 'efficient', 'sustainable', 'innovative',
            'creative', 'professional', 'expert', 'advanced', 'important', 'essential',
            'fundamental', 'critical', 'necessary', 'possible', 'probable', 'potential',
            'current', 'recent', 'new', 'latest', 'best', 'good', 'great', 'high',
            'the', 'a', 'an', 'some', 'for', 'with', 'by', 'between', 'about', 'from',
            'to', 'according', 'as', 'when', 'where', 'because', 'although', 'if', 'but',
        ];

        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $words[array_rand($words)];
        }

        return implode(' ', $result);
    }

    /**
     * Generate an example URL.
     */
    private function generateUrl(): string
    {
        $domains = [
            'example.com', 'news.org', 'technology.info', 'science.org',
            'innovation.net', 'economy.com', 'culture.org', 'current-affairs.info',
        ];

        $paths = [
            'article', 'news', 'analysis', 'study', 'report', 'interview',
            'opinion', 'summary', 'guide', 'tutorial', 'research', 'discovery',
        ];

        $domain = $domains[array_rand($domains)];
        $path = $paths[array_rand($paths)];
        $id = rand(1000, 9999);

        return "https://www.{$domain}/{$path}/{$id}";
    }

    /**
     * Generate a thumbnail image URL.
     */
    private function generateThumbnailUrl(): string
    {
        $width = 480;
        $height = 320;
        $randomId = rand(1, 1000);

        return "https://picsum.photos/id/{$randomId}/{$width}/{$height}";
    }
}
