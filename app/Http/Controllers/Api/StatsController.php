<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class StatsController extends Controller
{
    private const CACHE_TTL = 300; // 5 minutes

    public function general(): JsonResponse
    {
        $stats = Cache::remember('stats:general', self::CACHE_TTL, fn () => [
            'total_users' => User::count(),
            'active_users_today' => User::whereDate('updated_at', today())->count(),
            'total_posts' => Post::count(),
            'total_comments' => Comment::count(),
            'posts_today' => Post::whereDate('created_at', today())->count(),
            'comments_today' => Comment::whereDate('created_at', today())->count(),
        ]);

        return response()->json($stats);
    }

    public function content(): JsonResponse
    {
        $stats = Cache::remember('stats:content', self::CACHE_TTL, function () {
            $postsByLanguage = Post::selectRaw('language_code, COUNT(*) as count')
                ->groupBy('language_code')
                ->get()
                ->map(fn ($item) => ['language_code' => $item->language_code, 'count' => $item->count])
                ->values()
                ->toArray();

            return [
                'published_posts' => Post::where('status', Post::STATUS_PUBLISHED)->count(),
                'pending_posts' => Post::where('status', Post::STATUS_PENDING)->count(),
                'posts_last_24h' => Post::where('created_at', '>=', now()->subDay())->count(),
                'posts_last_7d' => Post::where('created_at', '>=', now()->subDays(7))->count(),
                'posts_by_language' => $postsByLanguage,
                'popular_tags' => [],
            ];
        });

        return response()->json($stats);
    }

    public function users(): JsonResponse
    {
        $stats = Cache::remember('stats:users', self::CACHE_TTL, function () {
            $totalUsers = User::count();
            $verifiedUsers = User::whereNotNull('email_verified_at')->count();
            $federatedUsers = User::whereNotNull('federated_id')->count();
            $telegramUsers = User::whereNotNull('telegram_id')->count();

            $confirmedUsers = User::where(function ($query): void {
                $query->whereNotNull('email_verified_at')
                    ->orWhereNotNull('federated_id')
                    ->orWhereNotNull('telegram_id');
            })->count();

            return [
                'total_users' => $totalUsers,
                'confirmed_users' => $confirmedUsers,
                'active_users_today' => User::whereDate('updated_at', today())->count(),
                'active_users_week' => User::where('updated_at', '>=', now()->subWeek())->count(),
                'active_users_month' => User::where('updated_at', '>=', now()->subMonth())->count(),
                'new_users_today' => User::whereDate('created_at', today())->count(),
                'new_users_week' => User::where('created_at', '>=', now()->subWeek())->count(),
                'new_users_month' => User::where('created_at', '>=', now()->subMonth())->count(),
                'verified_users' => $verifiedUsers,
                'federated_users' => $federatedUsers,
                'telegram_users' => $telegramUsers,
                'verification_rate' => $totalUsers > 0 ? round(($verifiedUsers / $totalUsers) * 100, 1) : 0,
                'confirmation_rate' => $totalUsers > 0 ? round(($confirmedUsers / $totalUsers) * 100, 1) : 0,
                'top_karma_users' => User::orderBy('karma_points', 'desc')
                    ->take(10)
                    ->get(['id', 'username', 'display_name', 'karma_points'])
                    ->toArray(),
            ];
        });

        return response()->json($stats);
    }

    public function engagement(): JsonResponse
    {
        $stats = Cache::remember('stats:engagement', self::CACHE_TTL, function () {
            $totalPosts = Post::count();
            $totalComments = Comment::count();
            $totalVotes = Vote::count();

            return [
                'total_votes' => $totalVotes,
                'votes_last_24h' => Vote::where('created_at', '>=', now()->subDay())->count(),
                'votes_last_7d' => Vote::where('created_at', '>=', now()->subDays(7))->count(),
                'comments_last_24h' => Comment::where('created_at', '>=', now()->subDay())->count(),
                'comments_last_7d' => Comment::where('created_at', '>=', now()->subDays(7))->count(),
                'avg_comments_per_post' => $totalPosts > 0 ? round($totalComments / $totalPosts, 1) : 0,
                'avg_votes_per_post' => $totalPosts > 0 ? round($totalVotes / $totalPosts, 1) : 0,
            ];
        });

        return response()->json($stats);
    }

    public function trending(): JsonResponse
    {
        $stats = [
            'trending_posts' => [],
            'trending_tags' => [],
            'trending_users' => [],
        ];

        return response()->json($stats);
    }
}
