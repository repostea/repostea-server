<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\SpamSetting;
use Illuminate\Support\Facades\Log;

final class DuplicateContentDetector
{
    /**
     * Check if a post title/content is similar to recent posts by the same user.
     *
     * @return array ['is_duplicate' => bool, 'similarity' => float, 'duplicate_post' => Post|null]
     */
    public function checkDuplicatePost(int $userId, string $title, ?string $content = null, int $hoursToCheck = 24, ?int $excludePostId = null): array
    {
        // Check if duplicate detection is enabled
        if (! SpamSetting::getValue('duplicate_detection_enabled', true)) {
            return ['is_duplicate' => false, 'similarity' => 0, 'duplicate_post' => null];
        }

        $threshold = SpamSetting::getValue('duplicate_similarity_threshold', 0.85);
        $hoursToCheck = SpamSetting::getValue('duplicate_check_hours', 24);

        // Get recent posts by this user (excluding current post to avoid self-comparison)
        $query = Post::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours($hoursToCheck));

        if ($excludePostId) {
            $query->where('id', '!=', $excludePostId);
        }

        $recentPosts = $query->get(['id', 'title', 'content', 'created_at']);

        if ($recentPosts->isEmpty()) {
            return ['is_duplicate' => false, 'similarity' => 0, 'duplicate_post' => null];
        }

        $maxSimilarity = 0;
        $duplicatePost = null;

        foreach ($recentPosts as $post) {
            // Calculate similarity for title
            $titleSimilarity = $this->calculateSimilarity($title, $post->title);

            // Calculate similarity for content if both exist
            $contentSimilarity = 0;
            if ($content && $post->content) {
                $contentSimilarity = $this->calculateSimilarity($content, $post->content);
            }

            // Overall similarity (weighted average: title 70%, content 30%)
            $similarity = $content && $post->content
                ? ($titleSimilarity * 0.7) + ($contentSimilarity * 0.3)
                : $titleSimilarity;

            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
                $duplicatePost = $post;
            }

            // Early exit if we found a very high similarity
            if ($similarity >= $threshold) {
                break;
            }
        }

        $isDuplicate = $maxSimilarity >= $threshold;

        if ($isDuplicate) {
            Log::info('Duplicate post detected', [
                'user_id' => $userId,
                'similarity' => $maxSimilarity,
                'duplicate_post_id' => $duplicatePost?->id,
            ]);
        }

        return [
            'is_duplicate' => $isDuplicate,
            'similarity' => $maxSimilarity,
            'duplicate_post' => $duplicatePost,
        ];
    }

    /**
     * Check if a comment is similar to recent comments by the same user.
     *
     * @return array ['is_duplicate' => bool, 'similarity' => float, 'duplicate_comment' => Comment|null]
     */
    public function checkDuplicateComment(int $userId, string $content, int $hoursToCheck = 24, ?int $excludeCommentId = null): array
    {
        // Check if duplicate detection is enabled
        if (! SpamSetting::getValue('duplicate_detection_enabled', true)) {
            return ['is_duplicate' => false, 'similarity' => 0, 'duplicate_comment' => null];
        }

        $threshold = SpamSetting::getValue('duplicate_similarity_threshold', 0.85);
        $hoursToCheck = SpamSetting::getValue('duplicate_check_hours', 24);

        // Get recent comments by this user (excluding current comment to avoid self-comparison)
        $query = Comment::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours($hoursToCheck));

        if ($excludeCommentId) {
            $query->where('id', '!=', $excludeCommentId);
        }

        $recentComments = $query->get(['id', 'content', 'created_at']);

        if ($recentComments->isEmpty()) {
            return ['is_duplicate' => false, 'similarity' => 0, 'duplicate_comment' => null];
        }

        $maxSimilarity = 0;
        $duplicateComment = null;

        foreach ($recentComments as $comment) {
            $similarity = $this->calculateSimilarity($content, $comment->content);

            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
                $duplicateComment = $comment;
            }

            // Early exit if we found a very high similarity
            if ($similarity >= $threshold) {
                break;
            }
        }

        $isDuplicate = $maxSimilarity >= $threshold;

        if ($isDuplicate) {
            Log::info('Duplicate comment detected', [
                'user_id' => $userId,
                'similarity' => $maxSimilarity,
                'duplicate_comment_id' => $duplicateComment?->id,
            ]);
        }

        return [
            'is_duplicate' => $isDuplicate,
            'similarity' => $maxSimilarity,
            'duplicate_comment' => $duplicateComment,
        ];
    }

    /**
     * Calculate similarity between two strings using Levenshtein distance
     * Returns a value between 0 (completely different) and 1 (identical).
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        // Normalize strings
        $str1 = $this->normalizeString($str1);
        $str2 = $this->normalizeString($str2);

        if ($str1 === $str2) {
            return 1.0;
        }

        if ($str1 === '' || $str2 === '') {
            return 0.0;
        }

        // Use similar_text for better performance on long strings
        similar_text($str1, $str2, $percent);

        return $percent / 100;
    }

    /**
     * Normalize string for comparison.
     */
    protected function normalizeString(string $str): string
    {
        // Convert to lowercase
        $str = mb_strtolower($str);

        // Remove extra whitespace
        $str = preg_replace('/\s+/', ' ', $str);

        // Trim
        $str = trim($str);

        return $str;
    }

    /**
     * Check if user is posting too rapidly (rapid-fire detection).
     *
     * @param  string  $contentType  'post' or 'comment'
     *
     * @return array ['is_rapid_fire' => bool, 'count' => int, 'window_seconds' => int]
     */
    public function checkRapidFire(int $userId, string $contentType = 'post'): array
    {
        // Check if rapid-fire detection is enabled
        if (! SpamSetting::getValue('rapid_fire_enabled', true)) {
            return ['is_rapid_fire' => false, 'count' => 0, 'window_seconds' => 0];
        }

        $threshold = SpamSetting::getValue('rapid_fire_posts_limit', 5);
        $windowMinutes = SpamSetting::getValue('rapid_fire_minutes', 10);
        $windowSeconds = $windowMinutes * 60;

        $model = $contentType === 'post' ? Post::class : Comment::class;

        $count = $model::where('user_id', $userId)
            ->where('created_at', '>=', now()->subSeconds($windowSeconds))
            ->count();

        $isRapidFire = $count >= $threshold;

        if ($isRapidFire) {
            Log::warning('Rapid-fire posting detected', [
                'user_id' => $userId,
                'content_type' => $contentType,
                'count' => $count,
                'window_seconds' => $windowSeconds,
            ]);
        }

        return [
            'is_rapid_fire' => $isRapidFire,
            'count' => $count,
            'window_seconds' => $windowSeconds,
        ];
    }

    /**
     * Get comprehensive spam score for a user
     * Returns a score from 0 (not spam) to 100 (definitely spam).
     */
    public function getSpamScore(int $userId): array
    {
        // Check if spam score calculation is enabled
        if (! SpamSetting::getValue('spam_score_enabled', true)) {
            return [
                'score' => 0,
                'is_spam' => false,
                'risk_level' => 'minimal',
                'reasons' => [],
            ];
        }

        $score = 0;
        $reasons = [];

        // Check rapid-fire posting
        $rapidFirePost = $this->checkRapidFire($userId, 'post');
        if ($rapidFirePost['is_rapid_fire']) {
            $score += 30;
            $reasons[] = "Rapid-fire posting: {$rapidFirePost['count']} posts in {$rapidFirePost['window_seconds']}s";
        }

        // Check rapid-fire commenting
        $rapidFireComment = $this->checkRapidFire($userId, 'comment');
        if ($rapidFireComment['is_rapid_fire']) {
            $score += 25;
            $reasons[] = "Rapid-fire commenting: {$rapidFireComment['count']} comments in {$rapidFireComment['window_seconds']}s";
        }

        // Check for duplicate posts in last 24 hours
        $recentPosts = Post::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours(24))
            ->get(['title', 'content']);

        $duplicatePostCount = 0;
        foreach ($recentPosts as $i => $post1) {
            foreach ($recentPosts->slice($i + 1) as $post2) {
                $similarity = $this->calculateSimilarity($post1->title, $post2->title);
                if ($similarity >= 0.8) {
                    $duplicatePostCount++;
                }
            }
        }

        if ($duplicatePostCount > 0) {
            $score += min(30, $duplicatePostCount * 10);
            $reasons[] = "Found {$duplicatePostCount} duplicate/similar posts";
        }

        // Check account age (new accounts are more suspicious)
        $user = \App\Models\User::find($userId);
        if ($user) {
            $accountAgeDays = $user->created_at->diffInDays(now());
            if ($accountAgeDays < 1) {
                $score += 15;
                $reasons[] = 'Very new account (< 1 day old)';
            } elseif ($accountAgeDays < 7) {
                $score += 5;
                $reasons[] = 'New account (< 7 days old)';
            }
        }

        $spamThreshold = SpamSetting::getValue('spam_score_threshold', 70);

        return [
            'score' => min(100, $score),
            'is_spam' => $score >= $spamThreshold,
            'risk_level' => $this->getRiskLevel($score),
            'reasons' => $reasons,
        ];
    }

    /**
     * Get risk level based on spam score.
     */
    protected function getRiskLevel(int $score): string
    {
        if ($score >= 75) {
            return 'critical';
        }
        if ($score >= 50) {
            return 'high';
        }
        if ($score >= 25) {
            return 'medium';
        }
        if ($score >= 10) {
            return 'low';
        }

        return 'minimal';
    }
}
