<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KarmaHistory;
use App\Models\KarmaLevel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KarmaController extends Controller
{
    /**
     * Show the karma of a specific user.
     */
    public function show(User $user): JsonResponse
    {
        $karmaData = [
            'user_id' => $user->id,
            'karma_points' => $user->karma_points,
            'level' => $user->currentLevel ? [
                'id' => $user->currentLevel->id,
                'name' => $user->currentLevel->name,
                'badge' => $user->currentLevel->badge,
            ] : null,
            'next_level' => null,
        ];

        if ($user->currentLevel) {
            $nextLevel = KarmaLevel::where('required_karma', '>', $user->karma_points)
                ->orderBy('required_karma', 'asc')
                ->first();

            if ($nextLevel) {
                $karmaData['next_level'] = [
                    'id' => $nextLevel->id,
                    'name' => $nextLevel->name,
                    'badge' => $nextLevel->badge,
                    'required_karma' => $nextLevel->required_karma,
                    'points_needed' => $nextLevel->required_karma - $user->karma_points,
                ];
            }
        }

        $history = KarmaHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $karmaData['recent_history'] = $history->map(static fn ($entry) => [
            'amount' => $entry->amount,
            'source' => $entry->source,
            'description' => $entry->description,
            'date' => $entry->created_at,
        ]);

        return response()->json([
            'data' => $karmaData,
        ]);
    }

    /**
     * Show all karma levels.
     */
    public function levels(): JsonResponse
    {
        $levels = KarmaLevel::orderBy('required_karma', 'asc')->get();

        return response()->json([
            'data' => $levels,
        ]);
    }

    /**
     * Show the karma leaderboard.
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);

        $users = User::select('id', 'username', 'display_name', 'avatar', 'karma_points', 'highest_level_id')
            ->with('currentLevel:id,name,badge')
            ->orderBy('karma_points', 'desc')
            ->paginate($limit);

        return response()->json([
            'data' => [
                'data' => $users->items(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }
}
