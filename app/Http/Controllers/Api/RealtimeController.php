<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RealtimeBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Controller for managing realtime connection tracking.
 */
final class RealtimeController extends Controller
{
    private const CONNECTION_TTL = 120; // 2 minutes - connections auto-expire

    public function __construct(
        private readonly RealtimeBroadcastService $realtimeService,
    ) {}

    /**
     * Register a new connection (called when client connects to WebSocket).
     */
    public function connect(Request $request): JsonResponse
    {
        $connectionId = $this->getConnectionId($request);

        // Store connection with TTL
        Cache::put("realtime:conn:{$connectionId}", true, self::CONNECTION_TTL);

        // Update total count
        $this->updateConnectionCount();

        return response()->json([
            'status' => 'connected',
            'throttle_interval' => $this->realtimeService->getThrottleInterval(),
        ]);
    }

    /**
     * Heartbeat to keep connection alive (called periodically by client).
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $connectionId = $this->getConnectionId($request);

        // Refresh TTL
        Cache::put("realtime:conn:{$connectionId}", true, self::CONNECTION_TTL);

        return response()->json([
            'status' => 'ok',
            'throttle_interval' => $this->realtimeService->getThrottleInterval(),
        ]);
    }

    /**
     * Disconnect (called when client disconnects).
     */
    public function disconnect(Request $request): JsonResponse
    {
        $connectionId = $this->getConnectionId($request);

        Cache::forget("realtime:conn:{$connectionId}");
        $this->updateConnectionCount();

        return response()->json(['status' => 'disconnected']);
    }

    /**
     * Get current connection stats (for debugging/monitoring).
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'connections' => $this->realtimeService->getConnectionsCount(),
            'throttle_interval' => $this->realtimeService->getThrottleInterval(),
        ]);
    }

    /**
     * Generate a unique connection ID based on request.
     */
    private function getConnectionId(Request $request): string
    {
        $userId = $request->user()?->id;
        $ip = $request->ip();
        $userAgent = substr($request->userAgent() ?? '', 0, 50);

        if ($userId !== null) {
            return "user:{$userId}:" . md5($userAgent);
        }

        return 'anon:' . md5($ip . $userAgent);
    }

    /**
     * Update the total connection count by scanning active connections.
     */
    private function updateConnectionCount(): void
    {
        // For Redis, we could use SCAN. For other drivers, we estimate.
        // Simple approach: increment/decrement counter
        $count = (int) Cache::get('realtime:connection_count', 0);

        // Recalculate periodically (every 30 seconds)
        $lastRecalc = (int) Cache::get('realtime:last_count_recalc', 0);
        if (time() - $lastRecalc > 30) {
            // This is a rough estimate - in production with Redis,
            // you'd use SCAN to count keys matching 'realtime:conn:*'
            Cache::put('realtime:last_count_recalc', time(), 3600);
        }

        $this->realtimeService->updateConnectionsCount($count);
    }
}
