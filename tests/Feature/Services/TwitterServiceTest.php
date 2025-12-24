<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Sub;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\TwitterService;

beforeEach(function (): void {
    $this->service = app(TwitterService::class);
    $this->user = User::factory()->create();
    $this->sub = Sub::create([
        'name' => 'testsub',
        'display_name' => 'Test Sub',
        'slug' => 'testsub',
        'created_by' => $this->user->id,
        'color' => '#000000',
    ]);

    config(['app.client_url' => 'https://example.com']);
    config(['twitter.api_key' => null]);
    config(['twitter.api_secret' => null]);
    config(['twitter.access_token' => null]);
    config(['twitter.access_token_secret' => null]);
});

describe('Configuration', function (): void {
    test('isConfigured returns false when not configured', function (): void {
        expect($this->service->isConfigured())->toBeFalse();
    });

    test('isConfigured returns true when all keys present', function (): void {
        config([
            'twitter.api_key' => 'key',
            'twitter.api_secret' => 'secret',
            'twitter.access_token' => 'token',
            'twitter.access_token_secret' => 'token_secret',
        ]);

        $service = app(TwitterService::class);

        expect($service->isConfigured())->toBeTrue();
    });

    test('isAutoPostEnabled reads from SystemSetting first', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);

        expect($this->service->isAutoPostEnabled())->toBeTrue();
    });

    test('isAutoPostEnabled falls back to config', function (): void {
        config(['twitter.auto_post_enabled' => true]);

        expect($this->service->isAutoPostEnabled())->toBeTrue();
    });

    test('getMinVotesToPost reads from SystemSetting', function (): void {
        SystemSetting::set('twitter_min_votes', 100);

        expect($this->service->getMinVotesToPost())->toBe(100);
    });

    test('getMinVotesToPost falls back to config', function (): void {
        config(['twitter.min_votes_to_post' => 75]);

        expect($this->service->getMinVotesToPost())->toBe(75);
    });

    test('getPostDelayMinutes reads from SystemSetting', function (): void {
        SystemSetting::set('twitter_post_delay_minutes', 60);

        expect($this->service->getPostDelayMinutes())->toBe(60);
    });

    test('isAutoPostArticlesEnabled reads from SystemSetting', function (): void {
        SystemSetting::set('twitter_auto_post_articles', false);

        expect($this->service->isAutoPostArticlesEnabled())->toBeFalse();
    });

    test('getMaxDaysBack reads from SystemSetting', function (): void {
        SystemSetting::set('twitter_max_days_back', 7);

        expect($this->service->getMaxDaysBack())->toBe(7);
    });
});

describe('Tweet Formatting', function (): void {
    test('formatTweet includes title', function (): void {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'Test Post Title',
            'slug' => 'test-post-title',
        ]);

        $tweet = $this->service->formatTweet($post);

        expect($tweet)->toContain('Test Post Title');
    });

    test('formatTweet includes content summary for text posts', function (): void {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'Article Title',
            'content' => 'This is a longer article content that should be truncated appropriately for Twitter.',
            'slug' => 'article-title',
        ]);

        $tweet = $this->service->formatTweet($post);

        expect($tweet)->toContain('This is a longer article content');
    });

    test('formatTweet includes post URL', function (): void {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'URL Test',
            'slug' => 'url-test',
            'content' => null,
        ]);

        $tweet = $this->service->formatTweet($post);

        // Sub slug comes from the created sub
        expect($tweet)->toContain('https://example.com/s/');
        expect($tweet)->toContain('/url-test');
    });

    test('formatTweet includes hashtags when configured', function (): void {
        config(['twitter.default_hashtags' => ['Tech', 'News']]);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'Hashtag Test',
            'slug' => 'hashtag-test',
        ]);

        $tweet = $this->service->formatTweet($post);

        expect($tweet)->toContain('#Tech');
        expect($tweet)->toContain('#News');
    });

    test('formatTweet strips HTML from content', function (): void {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'HTML Test',
            'content' => '<p>This is <strong>HTML</strong> content</p>',
            'slug' => 'html-test',
        ]);

        $tweet = $this->service->formatTweet($post);

        expect($tweet)->not->toContain('<p>');
        expect($tweet)->not->toContain('<strong>');
        expect($tweet)->toContain('This is HTML content');
    });

    test('formatTweet truncates long content', function (): void {
        $longContent = str_repeat('Lorem ipsum dolor sit amet. ', 20);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'title' => 'Long Content',
            'content' => $longContent,
            'slug' => 'long-content',
        ]);

        $tweet = $this->service->formatTweet($post);

        // Content should be truncated with ellipsis
        expect($tweet)->toContain('...');
        expect(strlen($tweet))->toBeLessThan(500);
    });
});

describe('Post Tweet', function (): void {
    test('postTweet returns false when not configured', function (): void {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
        ]);

        $result = $this->service->postTweet($post);

        expect($result)->toBeFalse();
    });

    test('postTweet returns false when already posted', function (): void {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'twitter_posted_at' => now(),
        ]);

        $result = $this->service->postTweet($post);

        expect($result)->toBeFalse();
    });
});

describe('Auto-Post Criteria (Votes)', function (): void {
    test('shouldPostByVotes returns false when auto-post disabled', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', false);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => 'published',
            'frontpage_at' => now()->subHours(2),
            'votes_count' => 100,
        ]);

        expect($this->service->shouldPostByVotes($post))->toBeFalse();
    });

    test('shouldPostByVotes returns false when already posted', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'twitter_posted_at' => now(),
        ]);

        expect($this->service->shouldPostByVotes($post))->toBeFalse();
    });

    test('shouldPostByVotes returns false for unpublished post', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => 'pending',
            'votes_count' => 100,
        ]);

        expect($this->service->shouldPostByVotes($post))->toBeFalse();
    });

    test('shouldPostByVotes returns false when not on frontpage', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => 'published',
            'frontpage_at' => null,
            'votes_count' => 100,
        ]);

        expect($this->service->shouldPostByVotes($post))->toBeFalse();
    });

    test('shouldPostByVotes returns false when delay not met', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);
        SystemSetting::set('twitter_post_delay_minutes', 30);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => 'published',
            'frontpage_at' => now()->subMinutes(10),
            'votes_count' => 100,
        ]);

        expect($this->service->shouldPostByVotes($post))->toBeFalse();
    });

    test('shouldPostByVotes returns false when votes below minimum', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);
        SystemSetting::set('twitter_min_votes', 50);
        SystemSetting::set('twitter_post_delay_minutes', 30);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => 'published',
            'frontpage_at' => now()->subHours(2),
            'votes_count' => 25,
        ]);

        expect($this->service->shouldPostByVotes($post))->toBeFalse();
    });

    test('shouldPostByVotes returns true when all criteria met', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);
        SystemSetting::set('twitter_min_votes', 50);
        SystemSetting::set('twitter_post_delay_minutes', 30);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => 'published',
            'frontpage_at' => now()->subHours(2),
            'votes_count' => 75,
        ]);

        expect($this->service->shouldPostByVotes($post))->toBeTrue();
    });
});

describe('Auto-Post Criteria (Original Articles)', function (): void {
    test('shouldPostAsOriginalArticle returns false when auto-post disabled', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', false);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'content_type' => 'text',
            'status' => 'published',
            'frontpage_at' => now()->subHours(2),
        ]);

        expect($this->service->shouldPostAsOriginalArticle($post))->toBeFalse();
    });

    test('shouldPostAsOriginalArticle returns false when articles posting disabled', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);
        SystemSetting::set('twitter_auto_post_articles', false);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'content_type' => 'text',
            'status' => 'published',
            'frontpage_at' => now()->subHours(2),
        ]);

        expect($this->service->shouldPostAsOriginalArticle($post))->toBeFalse();
    });

    test('shouldPostAsOriginalArticle returns false for non-text posts', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);
        SystemSetting::set('twitter_auto_post_articles', true);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'content_type' => 'link',
            'status' => 'published',
            'frontpage_at' => now()->subHours(2),
        ]);

        expect($this->service->shouldPostAsOriginalArticle($post))->toBeFalse();
    });

    test('shouldPostAsOriginalArticle returns false when not on frontpage', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);
        SystemSetting::set('twitter_auto_post_articles', true);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'content_type' => 'text',
            'status' => 'published',
            'frontpage_at' => null,
        ]);

        expect($this->service->shouldPostAsOriginalArticle($post))->toBeFalse();
    });

    test('shouldPostAsOriginalArticle returns true when all criteria met', function (): void {
        SystemSetting::set('twitter_auto_post_enabled', true);
        SystemSetting::set('twitter_auto_post_articles', true);
        SystemSetting::set('twitter_post_delay_minutes', 30);

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'content_type' => 'text',
            'status' => 'published',
            'frontpage_at' => now()->subHours(2),
        ]);

        expect($this->service->shouldPostAsOriginalArticle($post))->toBeTrue();
    });
});
