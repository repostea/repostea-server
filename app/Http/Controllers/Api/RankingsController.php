<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class RankingsController extends Controller
{
    private const CACHE_TTL = 3600; // 1 hour (rankings change slowly)

    /**
     * Get karma ranking with optional timeframe filter.
     */
    public function karma(Request $request): JsonResponse
    {
        $timeframe = $request->input('timeframe', 'all');
        $limit = min((int) $request->input('limit', 100), 100);
        $page = (int) $request->input('page', 1);

        $cacheKey = "rankings:karma:{$timeframe}:{$limit}:{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($timeframe, $limit, $page) {
            $query = User::select('id', 'username', 'display_name', 'avatar', 'avatar_url', 'avatar_image_id', 'karma_points', 'highest_level_id')
                ->with(['currentLevel:id,name,badge', 'avatarImage'])
                ->where('is_guest', false)
                // Only include users who have had real interactions (voted, commented, or posted)
                ->where(function ($q): void {
                    $q->whereHas('votes')
                        ->orWhereHas('comments')
                        ->orWhereHas('posts');
                });

            // Apply timeframe filter using daily_karma_stats (much faster!)
            if ($timeframe !== 'all') {
                $dateFilter = $this->getDateFilter($timeframe);
                if ($dateFilter) {
                    // Join with aggregated daily stats
                    $query->leftJoin('daily_karma_stats', function ($join) use ($dateFilter): void {
                        $join->on('users.id', '=', 'daily_karma_stats.user_id')
                            ->where('daily_karma_stats.date', '>=', $dateFilter);
                    })
                        ->select('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.avatar_image_id', 'users.karma_points', 'users.highest_level_id')
                        ->selectRaw('COALESCE(SUM(daily_karma_stats.karma_earned), 0) as period_karma')
                        ->groupBy('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.avatar_image_id', 'users.karma_points', 'users.highest_level_id')
                        ->having('period_karma', '>', 0)
                        ->orderBy('period_karma', 'desc');
                } else {
                    $query->where('karma_points', '>', 0)
                        ->orderBy('karma_points', 'desc');
                }
            } else {
                $query->where('karma_points', '>', 0)
                    ->orderBy('karma_points', 'desc');
            }

            $offset = ($page - 1) * $limit;
            $users = $query->skip($offset)->take($limit)->get();

            // Get total count for pagination (only users with karma > 0 and real interactions)
            // Cache the total count separately as it's expensive and rarely changes
            $total = (int) Cache::tags(['rankings'])->remember("rankings:karma:total:{$timeframe}", self::CACHE_TTL, function () use ($timeframe) {
                // Base query: non-guest users with real interactions
                $baseQuery = User::where('is_guest', false)
                    ->where(function ($q): void {
                        $q->whereHas('votes')
                            ->orWhereHas('comments')
                            ->orWhereHas('posts');
                    });

                if ($timeframe === 'all') {
                    return $baseQuery->where('karma_points', '>', 0)->count();
                }

                // For timeframe filters, count with subquery
                $dateFilter = $this->getDateFilter($timeframe);
                if (! $dateFilter) {
                    return $baseQuery->where('karma_points', '>', 0)->count();
                }

                // Use subquery to count users with karma in period and real interactions
                return DB::table(DB::raw('(
                    SELECT users.id
                    FROM users
                    LEFT JOIN daily_karma_stats ON users.id = daily_karma_stats.user_id
                        AND daily_karma_stats.date >= ?
                    WHERE users.is_guest = 0
                        AND (
                            EXISTS (SELECT 1 FROM votes WHERE votes.user_id = users.id)
                            OR EXISTS (SELECT 1 FROM comments WHERE comments.user_id = users.id)
                            OR EXISTS (SELECT 1 FROM posts WHERE posts.user_id = users.id)
                        )
                    GROUP BY users.id
                    HAVING COALESCE(SUM(daily_karma_stats.karma_earned), 0) > 0
                ) as counted_users'))
                    ->setBindings([$dateFilter])
                    ->count();
            });

            return [
                'users' => $users->map(fn ($user) => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'avatar' => $user->avatar,
                    'karma_points' => $timeframe !== 'all' && isset($user->period_karma)
                        ? (int) $user->period_karma
                        : $user->karma_points,
                    'level' => $user->currentLevel ? [
                        'id' => $user->currentLevel->id,
                        'name' => __($user->currentLevel->name),
                        'badge' => $user->currentLevel->badge,
                    ] : null,
                ]),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'timeframe' => $timeframe,
        ]);
    }

    /**
     * Get posts ranking with optional timeframe filter.
     */
    public function posts(Request $request): JsonResponse
    {
        $timeframe = $request->input('timeframe', 'all');
        $limit = min((int) $request->input('limit', 100), 100);
        $page = (int) $request->input('page', 1);

        $cacheKey = "rankings:posts:{$timeframe}:{$limit}:{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($timeframe, $limit, $page) {
            $dateFilter = $this->getDateFilter($timeframe);

            $query = User::select('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.avatar_image_id', 'users.karma_points', 'users.highest_level_id')
                ->with(['currentLevel:id,name,badge', 'avatarImage'])
                ->where('users.is_guest', false);

            if ($dateFilter) {
                $query->leftJoin('posts', function ($join) use ($dateFilter): void {
                    $join->on('users.id', '=', 'posts.user_id')
                        ->where('posts.created_at', '>=', $dateFilter);
                });
            } else {
                $query->leftJoin('posts', 'users.id', '=', 'posts.user_id');
            }

            $query->groupBy('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.avatar_image_id', 'users.karma_points', 'users.highest_level_id')
                ->selectRaw('COUNT(posts.id) as posts_count')
                ->orderBy('posts_count', 'desc')
                ->having('posts_count', '>', 0);

            $offset = ($page - 1) * $limit;
            $users = $query->skip($offset)->take($limit)->get();

            // Get total count (cached separately)
            $total = (int) Cache::tags(['rankings'])->remember("rankings:posts:total:{$timeframe}", self::CACHE_TTL, function () use ($dateFilter) {
                return User::where('is_guest', false)
                    ->whereHas('posts', function ($q) use ($dateFilter): void {
                        if ($dateFilter) {
                            $q->where('created_at', '>=', $dateFilter);
                        }
                    })
                    ->count();
            });

            return [
                'users' => $users->map(fn ($user) => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'avatar' => $user->avatar,
                    'posts_count' => $user->posts_count,
                    'karma_points' => $user->karma_points,
                    'level' => $user->currentLevel ? [
                        'id' => $user->currentLevel->id,
                        'name' => __($user->currentLevel->name),
                        'badge' => $user->currentLevel->badge,
                    ] : null,
                ]),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'timeframe' => $timeframe,
        ]);
    }

    /**
     * Get comments ranking with optional timeframe filter.
     */
    public function comments(Request $request): JsonResponse
    {
        $timeframe = $request->input('timeframe', 'all');
        $limit = min((int) $request->input('limit', 100), 100);
        $page = (int) $request->input('page', 1);

        $cacheKey = "rankings:comments:{$timeframe}:{$limit}:{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($timeframe, $limit, $page) {
            $dateFilter = $this->getDateFilter($timeframe);

            $query = User::select('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.avatar_image_id', 'users.karma_points', 'users.highest_level_id')
                ->with(['currentLevel:id,name,badge', 'avatarImage'])
                ->where('users.is_guest', false);

            if ($dateFilter) {
                $query->leftJoin('comments', function ($join) use ($dateFilter): void {
                    $join->on('users.id', '=', 'comments.user_id')
                        ->where('comments.created_at', '>=', $dateFilter);
                });
            } else {
                $query->leftJoin('comments', 'users.id', '=', 'comments.user_id');
            }

            $query->groupBy('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.avatar_image_id', 'users.karma_points', 'users.highest_level_id')
                ->selectRaw('COUNT(comments.id) as comments_count')
                ->orderBy('comments_count', 'desc')
                ->having('comments_count', '>', 0);

            $offset = ($page - 1) * $limit;
            $users = $query->skip($offset)->take($limit)->get();

            // Get total count (cached separately)
            $total = (int) Cache::tags(['rankings'])->remember("rankings:comments:total:{$timeframe}", self::CACHE_TTL, function () use ($dateFilter) {
                return User::where('is_guest', false)
                    ->whereHas('comments', function ($q) use ($dateFilter): void {
                        if ($dateFilter) {
                            $q->where('created_at', '>=', $dateFilter);
                        }
                    })
                    ->count();
            });

            return [
                'users' => $users->map(fn ($user) => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'avatar' => $user->avatar,
                    'comments_count' => $user->comments_count,
                    'karma_points' => $user->karma_points,
                    'level' => $user->currentLevel ? [
                        'id' => $user->currentLevel->id,
                        'name' => __($user->currentLevel->name),
                        'badge' => $user->currentLevel->badge,
                    ] : null,
                ]),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'timeframe' => $timeframe,
        ]);
    }

    /**
     * Get streaks ranking (no timeframe, shows longest streaks).
     */
    public function streaks(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 100), 100);
        $page = (int) $request->input('page', 1);

        $cacheKey = "rankings:streaks:{$limit}:{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($limit, $page) {
            $query = User::select('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.avatar_image_id', 'users.karma_points', 'users.highest_level_id')
                ->with(['currentLevel:id,name,badge', 'avatarImage'])
                ->join('user_streaks', 'users.id', '=', 'user_streaks.user_id')
                ->where('users.is_guest', false)
                ->selectRaw('user_streaks.current_streak, user_streaks.longest_streak')
                ->orderBy('user_streaks.longest_streak', 'desc')
                ->where('user_streaks.longest_streak', '>', 0);

            $offset = ($page - 1) * $limit;
            $users = $query->skip($offset)->take($limit)->get();

            // Get total count (cached separately)
            $total = (int) Cache::tags(['rankings'])->remember('rankings:streaks:total', self::CACHE_TTL, function () {
                return User::where('is_guest', false)
                    ->whereHas('streak', function ($q): void {
                        $q->where('longest_streak', '>', 0);
                    })
                    ->count();
            });

            return [
                'users' => $users->map(fn ($user) => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'avatar' => $user->avatar,
                    'current_streak' => $user->current_streak,
                    'longest_streak' => $user->longest_streak,
                    'karma_points' => $user->karma_points,
                    'level' => $user->currentLevel ? [
                        'id' => $user->currentLevel->id,
                        'name' => __($user->currentLevel->name),
                        'badge' => $user->currentLevel->badge,
                    ] : null,
                ]),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get achievements ranking (shows users with most achievements).
     */
    public function achievements(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 100), 100);
        $page = (int) $request->input('page', 1);

        $cacheKey = "rankings:achievements:{$limit}:{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($limit, $page) {
            $query = User::select('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.avatar_image_id', 'users.karma_points', 'users.highest_level_id')
                ->with(['currentLevel:id,name,badge', 'avatarImage'])
                ->leftJoin('achievement_user', 'users.id', '=', 'achievement_user.user_id')
                ->where('users.is_guest', false)
                ->whereNotNull('achievement_user.unlocked_at')
                ->groupBy('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.avatar_image_id', 'users.karma_points', 'users.highest_level_id')
                ->selectRaw('COUNT(achievement_user.achievement_id) as achievements_count')
                ->orderBy('achievements_count', 'desc')
                ->having('achievements_count', '>', 0);

            $offset = ($page - 1) * $limit;
            $users = $query->skip($offset)->take($limit)->get();

            // Get total count (cached separately)
            $total = (int) Cache::tags(['rankings'])->remember('rankings:achievements:total', self::CACHE_TTL, function () {
                return User::where('is_guest', false)
                    ->whereHas('achievements', function ($q): void {
                        $q->whereNotNull('unlocked_at');
                    })
                    ->count();
            });

            return [
                'users' => $users->map(fn ($user) => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'avatar' => $user->avatar,
                    'achievements_count' => $user->achievements_count,
                    'karma_points' => $user->karma_points,
                    'level' => $user->currentLevel ? [
                        'id' => $user->currentLevel->id,
                        'name' => __($user->currentLevel->name),
                        'badge' => $user->currentLevel->badge,
                    ] : null,
                ]),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get date filter based on timeframe.
     */
    private function getDateFilter(?string $timeframe): ?string
    {
        return match ($timeframe) {
            'today' => now()->startOfDay()->toDateTimeString(),
            'week' => now()->startOfWeek()->toDateTimeString(),
            'month' => now()->startOfMonth()->toDateTimeString(),
            default => null,
        };
    }

    /**
     * Get user's karma history (daily breakdown).
     */
    public function userKarmaHistory(Request $request, int $userId): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 365); // Max 1 year

        $history = DB::table('daily_karma_stats')
            ->where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->limit($days)
            ->get(['date', 'karma_earned']);

        // Calculate stats
        $totalKarma = $history->sum('karma_earned');
        $avgDaily = $history->isNotEmpty() ? round($totalKarma / $history->count(), 2) : 0;
        $bestDay = $history->sortByDesc('karma_earned')->first();

        return response()->json([
            'data' => [
                'history' => $history->reverse()->values(), // Oldest first for charts
                'stats' => [
                    'total_karma_period' => $totalKarma,
                    'average_daily' => $avgDaily,
                    'best_day' => $bestDay ? [
                        'date' => $bestDay->date,
                        'karma' => $bestDay->karma_earned,
                    ] : null,
                    'days_active' => $history->where('karma_earned', '>', 0)->count(),
                ],
            ],
        ]);
    }

    /**
     * Clear rankings cache (admin only).
     */
    public function clearCache(): JsonResponse
    {
        // Clear all rankings cache keys
        $patterns = ['karma', 'posts', 'comments', 'streaks', 'achievements'];
        $timeframes = ['all', 'today', 'week', 'month'];

        $cleared = 0;

        // Clear paginated rankings
        foreach ($patterns as $pattern) {
            foreach ($timeframes as $timeframe) {
                for ($page = 1; $page <= 10; $page++) {
                    for ($limit = 10; $limit <= 100; $limit += 10) {
                        if (Cache::forget("rankings:{$pattern}:{$timeframe}:{$limit}:{$page}")) {
                            $cleared++;
                        }
                    }
                }
            }
        }

        // Clear total count caches
        foreach (['karma', 'posts', 'comments'] as $pattern) {
            foreach ($timeframes as $timeframe) {
                if (Cache::forget("rankings:{$pattern}:total:{$timeframe}")) {
                    $cleared++;
                }
            }
        }

        // Clear streaks and achievements totals (no timeframe)
        if (Cache::forget('rankings:streaks:total')) {
            $cleared++;
        }
        if (Cache::forget('rankings:achievements:total')) {
            $cleared++;
        }

        return response()->json([
            'message' => 'Rankings cache cleared successfully',
            'keys_cleared' => $cleared,
        ]);
    }
}
