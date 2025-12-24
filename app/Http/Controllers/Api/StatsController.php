<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class StatsController extends Controller
{
    public function general(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'active_users_today' => User::whereDate('updated_at', today())->count(),
            'total_posts' => Post::count(),
            'total_comments' => Comment::count(),
            'posts_today' => Post::whereDate('created_at', today())->count(),
            'comments_today' => Comment::whereDate('created_at', today())->count(),
        ];

        return response()->json($stats);
    }

    public function content(): JsonResponse
    {
        $postsByLanguage = Post::selectRaw('language_code, COUNT(*) as count')
            ->groupBy('language_code')
            ->get()
            ->map(fn ($item) => ['language_code' => $item->language_code, 'count' => $item->count])
            ->values()
            ->toArray();

        $stats = [
            'published_posts' => Post::where('status', 'published')->count(),
            'pending_posts' => Post::where('status', 'pending')->count(),
            'posts_last_24h' => Post::where('created_at', '>=', now()->subDay())->count(),
            'posts_last_7d' => Post::where('created_at', '>=', now()->subDays(7))->count(),
            'posts_by_language' => $postsByLanguage,
            'popular_tags' => [], // Implementar si se necesita
        ];

        return response()->json($stats);
    }

    public function users(): JsonResponse
    {
        $totalUsers = User::count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $federatedUsers = User::whereNotNull('federated_id')->count();
        $telegramUsers = User::whereNotNull('telegram_id')->count();

        // Confirmed users: verified by email OR authenticated via OAuth (Mastodon/Telegram)
        // We avoid counting duplicates (users who might have verified email AND be from OAuth)
        $confirmedUsers = User::where(function ($query): void {
            $query->whereNotNull('email_verified_at')
                ->orWhereNotNull('federated_id')
                ->orWhereNotNull('telegram_id');
        })->count();

        $stats = [
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

        return response()->json($stats);
    }

    public function engagement(): JsonResponse
    {
        $totalPosts = Post::count();
        $totalComments = Comment::count();

        // Calculate total votes (sum of positive and negative votes)
        $totalVotes = DB::table('votes')->count();
        $votesLast24h = DB::table('votes')->where('created_at', '>=', now()->subDay())->count();
        $votesLast7d = DB::table('votes')->where('created_at', '>=', now()->subDays(7))->count();

        // Comments
        $commentsLast24h = Comment::where('created_at', '>=', now()->subDay())->count();
        $commentsLast7d = Comment::where('created_at', '>=', now()->subDays(7))->count();

        // Calculate averages
        $avgCommentsPerPost = $totalPosts > 0 ? round($totalComments / $totalPosts, 1) : 0;
        $avgVotesPerPost = $totalPosts > 0 ? round($totalVotes / $totalPosts, 1) : 0;

        $stats = [
            'total_votes' => $totalVotes,
            'votes_last_24h' => $votesLast24h,
            'votes_last_7d' => $votesLast7d,
            'comments_last_24h' => $commentsLast24h,
            'comments_last_7d' => $commentsLast7d,
            'avg_comments_per_post' => $avgCommentsPerPost,
            'avg_votes_per_post' => $avgVotesPerPost,
        ];

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
