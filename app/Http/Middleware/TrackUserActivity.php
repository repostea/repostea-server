<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\StreakService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Log;

final class TrackUserActivity
{
    protected $streakService;

    public function __construct(StreakService $streakService)
    {
        $this->streakService = $streakService;
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (Auth::check()) {
            try {
                $this->streakService->recordActivity(Auth::user());
            } catch (Exception $e) {
                Log::error('Error tracking user activity: ' . $e->getMessage());
            }
        }

        return $response;
    }
}
