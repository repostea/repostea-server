<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RateLimitLog;
use App\Models\User;
use App\Models\UserBan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

final class AbuseMonitoringController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the abuse monitoring dashboard.
     */
    public function index(Request $request)
    {
        $this->authorize('access-admin');

        $timeRange = (int) $request->get('hours', 24);

        // Get overall statistics
        $stats = [
            'total_violations' => RateLimitLog::recent($timeRange)->count(),
            'unique_violators' => RateLimitLog::recent($timeRange)
                ->distinct('user_id')
                ->whereNotNull('user_id')
                ->count('user_id'),
            'unique_ips' => RateLimitLog::recent($timeRange)
                ->distinct('ip_address')
                ->count('ip_address'),
            'auto_bans_issued' => UserBan::where('banned_by', null)
                ->where('created_at', '>=', now()->subHours($timeRange))
                ->count(),
        ];

        // Get violations by action type
        $violationsByAction = RateLimitLog::getViolationsByAction($timeRange);

        // Get violations over time for chart
        $violationsOverTime = RateLimitLog::getViolationsOverTime($timeRange);

        // Get top offenders
        $topOffenders = RateLimitLog::select('user_id')
            ->selectRaw('COUNT(*) as violation_count')
            ->selectRaw('COUNT(DISTINCT action) as unique_actions')
            ->selectRaw('MAX(created_at) as last_violation')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subHours($timeRange))
            ->groupBy('user_id')
            ->orderByDesc('violation_count')
            ->limit(20)
            ->with(['user' => function ($query): void {
                $query->select('id', 'username', 'email', 'karma_points', 'created_at')
                    ->withCount(['bans' => function ($q): void {
                        $q->where('is_active', true);
                    }]);
            }])
            ->get();

        // Get suspicious IPs (guest violations)
        $suspiciousIps = RateLimitLog::select('ip_address')
            ->selectRaw('COUNT(*) as violation_count')
            ->selectRaw('COUNT(DISTINCT action) as unique_actions')
            ->selectRaw('MAX(created_at) as last_violation')
            ->whereNull('user_id')
            ->where('created_at', '>=', now()->subHours($timeRange))
            ->groupBy('ip_address')
            ->having('violation_count', '>=', 5)
            ->orderByDesc('violation_count')
            ->limit(20)
            ->get();

        // Recent violations (last 50)
        $recentViolations = RateLimitLog::with('user:id,username,email,karma_points')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('admin.abuse.index', compact(
            'stats',
            'violationsByAction',
            'violationsOverTime',
            'topOffenders',
            'suspiciousIps',
            'recentViolations',
            'timeRange',
        ));
    }

    /**
     * Show detailed violations for a specific user.
     */
    public function userViolations(Request $request, User $user)
    {
        $this->authorize('access-admin');

        $timeRange = (int) $request->get('hours', 168); // Default 7 days

        $violations = RateLimitLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours($timeRange))
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $violationsByAction = RateLimitLog::select('action')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('MAX(created_at) as last_violation')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours($timeRange))
            ->groupBy('action')
            ->orderByDesc('count')
            ->get();

        $stats = [
            'total_violations' => $violations->total(),
            'unique_actions' => $violationsByAction->count(),
            'first_violation' => RateLimitLog::where('user_id', $user->id)->min('created_at'),
            'last_violation' => RateLimitLog::where('user_id', $user->id)->max('created_at'),
        ];

        return view('admin.abuse.user', compact('user', 'violations', 'violationsByAction', 'stats', 'timeRange'));
    }

    /**
     * Show violations from a specific IP address.
     */
    public function ipViolations(Request $request, string $ip)
    {
        $this->authorize('access-admin');

        $timeRange = (int) $request->get('hours', 168); // Default 7 days

        $violations = RateLimitLog::where('ip_address', $ip)
            ->where('created_at', '>=', now()->subHours($timeRange))
            ->with('user:id,username,email')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $violationsByAction = RateLimitLog::select('action')
            ->selectRaw('COUNT(*) as count')
            ->where('ip_address', $ip)
            ->where('created_at', '>=', now()->subHours($timeRange))
            ->groupBy('action')
            ->orderByDesc('count')
            ->get();

        $usersFromIp = RateLimitLog::select('user_id')
            ->selectRaw('COUNT(*) as violation_count')
            ->where('ip_address', $ip)
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subHours($timeRange))
            ->groupBy('user_id')
            ->with('user:id,username,email,karma_points')
            ->orderByDesc('violation_count')
            ->get();

        return view('admin.abuse.ip', compact('ip', 'violations', 'violationsByAction', 'usersFromIp', 'timeRange'));
    }

    /**
     * Get real-time statistics (for AJAX polling).
     */
    public function realtimeStats(Request $request)
    {
        $this->authorize('access-admin');

        $minutes = (int) $request->get('minutes', 60);

        $stats = [
            'violations_last_hour' => RateLimitLog::where('created_at', '>=', now()->subHour())->count(),
            'violations_last_5_min' => RateLimitLog::where('created_at', '>=', now()->subMinutes(5))->count(),
            'unique_users_last_hour' => RateLimitLog::where('created_at', '>=', now()->subHour())
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),
            'top_action_last_hour' => RateLimitLog::select('action')
                ->selectRaw('COUNT(*) as count')
                ->where('created_at', '>=', now()->subHour())
                ->groupBy('action')
                ->orderByDesc('count')
                ->first(),
            'recent_violations' => RateLimitLog::with('user:id,username')
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ];

        // If request wants JSON (AJAX), return JSON
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($stats);
        }

        // Otherwise, return the HTML view
        return view('admin.abuse.realtime');
    }

    /**
     * Blacklist an IP address.
     */
    public function blacklistIp(Request $request)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'ip_address' => 'required|ip',
            'duration_hours' => 'required|integer|min:1|max:8760', // Max 1 year
            'reason' => 'required|string|max:500',
        ]);

        $key = "ip_blacklist:{$validated['ip_address']}";
        $ttl = now()->addHours((int) $validated['duration_hours']);

        Cache::put($key, [
            'reason' => $validated['reason'],
            'blacklisted_by' => Auth::id(),
            'blacklisted_at' => now()->toDateTimeString(),
        ], $ttl);

        return redirect()->back()->with('success', "IP {$validated['ip_address']} has been blacklisted for {$validated['duration_hours']} hours.");
    }

    /**
     * Remove IP from blacklist.
     */
    public function removeIpBlacklist(Request $request)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'ip_address' => 'required|ip',
        ]);

        $key = "ip_blacklist:{$validated['ip_address']}";
        Cache::forget($key);

        return redirect()->back()->with('success', "IP {$validated['ip_address']} has been removed from blacklist.");
    }

    /**
     * Export violations data as CSV.
     */
    public function export(Request $request)
    {
        $this->authorize('access-admin');

        $timeRange = (int) $request->get('hours', 24);

        $violations = RateLimitLog::with('user:id,username,email')
            ->where('created_at', '>=', now()->subHours($timeRange))
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'rate_limit_violations_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($violations): void {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'ID',
                'Date/Time',
                'User ID',
                'Username',
                'Email',
                'Action',
                'IP Address',
                'Attempts',
                'Max Attempts',
                'Endpoint',
                'Method',
            ]);

            // CSV data
            foreach ($violations as $violation) {
                fputcsv($file, [
                    $violation->id,
                    $violation->created_at->toDateTimeString(),
                    $violation->user_id,
                    $violation->user?->username,
                    $violation->user?->email,
                    $violation->action,
                    $violation->ip_address,
                    $violation->attempts,
                    $violation->max_attempts,
                    $violation->endpoint,
                    $violation->method,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Clear old rate limit logs.
     */
    public function cleanupLogs(Request $request)
    {
        $this->authorize('access-admin');

        $deleted = RateLimitLog::cleanupOldLogs();

        return redirect()->back()->with('success', "Cleaned up {$deleted} old rate limit logs.");
    }

    /**
     * Update rate limit configuration (runtime).
     */
    public function updateConfig(Request $request)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'action' => 'required|string',
            'max_attempts' => 'required|integer|min:1|max:10000',
            'decay_minutes' => 'required|integer|min:1|max:10080',
        ]);

        // Note: This updates runtime cache, not the config file
        // For permanent changes, edit config/rate_limits.php
        $key = "rate_limit_override:{$validated['action']}";
        Cache::put($key, [
            'max_attempts' => $validated['max_attempts'],
            'decay_minutes' => $validated['decay_minutes'],
        ], now()->addDays(7));

        return redirect()->back()->with('success', "Rate limit for {$validated['action']} has been updated (temporary override).");
    }
}
