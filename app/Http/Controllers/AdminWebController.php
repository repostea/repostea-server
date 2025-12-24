<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use const PHP_OS_FAMILY;
use const PHP_VERSION;

use App\Helpers\ErrorHelper;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;

final class AdminWebController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the admin database dashboard.
     */
    public function index(): View
    {
        $this->authorize('admin-only');

        $mainConnection = config('database.default');
        $mediaConnection = 'media';

        $stats = [
            'main_database' => $this->getDatabaseSize($mainConnection),
            'media_database' => $this->getDatabaseSize($mediaConnection),
            'largest_tables' => $this->getLargestTables($mainConnection, 10),
            'disk_space' => $this->getDiskSpace(),
            'record_counts' => $this->getRecordCounts(),
            'index_stats' => $this->getIndexStats($mainConnection),
            'fragmentation' => $this->getFragmentation($mainConnection),
            'slow_queries' => $this->getSlowQueries($mainConnection),
            'queries_without_indexes' => $this->getQueriesWithoutIndexes($mainConnection),
        ];

        return view('admin.database', compact('stats'));
    }

    /**
     * Get the size of a database.
     */
    private function getDatabaseSize(string $connection): array
    {
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        try {
            if ($driver === 'mysql') {
                $result = DB::connection($connection)
                    ->select('SELECT
                        table_schema AS "database_name",
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS "size_mb"
                    FROM information_schema.TABLES
                    WHERE table_schema = ?
                    GROUP BY table_schema', [$database]);

                return [
                    'name' => $database,
                    'size_mb' => $result[0]->size_mb ?? 0,
                    'connection' => $connection,
                ];
            } elseif ($driver === 'sqlite') {
                $size = file_exists($database) ? filesize($database) : 0;

                return [
                    'name' => basename($database),
                    'size_mb' => round($size / 1024 / 1024, 2),
                    'connection' => $connection,
                ];
            }
        } catch (Exception $e) {
            return [
                'name' => $database,
                'size_mb' => 0,
                'connection' => $connection,
                'error' => ErrorHelper::getSafeError($e),
            ];
        }

        return [
            'name' => $database,
            'size_mb' => 0,
            'connection' => $connection,
        ];
    }

    /**
     * Get the largest tables in a database.
     */
    private function getLargestTables(string $connection, int $limit = 3): array
    {
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        try {
            if ($driver === 'mysql') {
                $results = DB::connection($connection)
                    ->select("SELECT
                        table_name,
                        ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                        table_rows
                    FROM information_schema.TABLES
                    WHERE table_schema = ?
                    ORDER BY (data_length + index_length) DESC
                    LIMIT {$limit}", [$database]);

                return array_map(fn ($row) => [
                    'name' => $row->TABLE_NAME ?? $row->table_name,
                    'size_mb' => (float) $row->size_mb,
                    'rows' => (int) ($row->TABLE_ROWS ?? $row->table_rows),
                ], $results);
            } elseif ($driver === 'sqlite') {
                $tables = DB::connection($connection)
                    ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

                $tableStats = [];
                foreach ($tables as $table) {
                    $count = DB::connection($connection)->table($table->name)->count();
                    $tableStats[] = [
                        'name' => $table->name,
                        'size_mb' => 0,
                        'rows' => $count,
                    ];
                }

                usort($tableStats, fn ($a, $b) => $b['rows'] <=> $a['rows']);

                return array_slice($tableStats, 0, $limit);
            }
        } catch (Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * Get disk space information.
     */
    private function getDiskSpace(): array
    {
        try {
            $dataPath = base_path();
            $freeSpace = disk_free_space($dataPath);
            $totalSpace = disk_total_space($dataPath);
            $usedSpace = $totalSpace - $freeSpace;

            return [
                'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'used_gb' => round($usedSpace / 1024 / 1024 / 1024, 2),
                'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'used_percent' => round(($usedSpace / $totalSpace) * 100, 1),
            ];
        } catch (Exception $e) {
            return [
                'total_gb' => 0,
                'used_gb' => 0,
                'free_gb' => 0,
                'used_percent' => 0,
                'error' => ErrorHelper::getSafeError($e),
            ];
        }
    }

    /**
     * Get record counts by type.
     */
    private function getRecordCounts(): array
    {
        $counts = [];
        $tables = ['users', 'posts', 'comments', 'votes', 'reports', 'achievements', 'notifications', 'legal_reports'];

        foreach ($tables as $table) {
            try {
                $counts[$table] = DB::table($table)->count();
            } catch (Exception $e) {
                // Skip tables that don't exist
                continue;
            }
        }

        return $counts;
    }

    /**
     * Get index statistics for tables.
     */
    private function getIndexStats(string $connection): array
    {
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        try {
            if ($driver === 'mysql') {
                $results = DB::connection($connection)
                    ->select('SELECT
                        TABLE_NAME as table_name,
                        ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb,
                        ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS index_mb,
                        ROUND((INDEX_LENGTH / (DATA_LENGTH + INDEX_LENGTH)) * 100, 1) AS index_percent
                    FROM information_schema.TABLES
                    WHERE table_schema = ?
                    AND (DATA_LENGTH + INDEX_LENGTH) > 0
                    ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
                    LIMIT 10', [$database]);

                return array_map(fn ($row) => [
                    'table' => $row->table_name ?? $row->TABLE_NAME,
                    'data_mb' => (float) $row->data_mb,
                    'index_mb' => (float) $row->index_mb,
                    'index_percent' => (float) $row->index_percent,
                ], $results);
            }
        } catch (Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * Get table fragmentation information.
     */
    private function getFragmentation(string $connection): array
    {
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        try {
            if ($driver === 'mysql') {
                $results = DB::connection($connection)
                    ->select('SELECT
                        TABLE_NAME as table_name,
                        ROUND(DATA_LENGTH / 1024 / 1024, 2) AS size_mb,
                        ROUND(DATA_FREE / 1024 / 1024, 2) AS free_mb,
                        ROUND((DATA_FREE / (DATA_LENGTH + DATA_FREE)) * 100, 1) AS fragmentation_percent
                    FROM information_schema.TABLES
                    WHERE table_schema = ?
                    AND DATA_FREE > 0
                    ORDER BY DATA_FREE DESC
                    LIMIT 10', [$database]);

                return array_map(fn ($row) => [
                    'table' => $row->table_name ?? $row->TABLE_NAME,
                    'size_mb' => (float) $row->size_mb,
                    'free_mb' => (float) $row->free_mb,
                    'fragmentation_percent' => (float) $row->fragmentation_percent,
                ], $results);
            }
        } catch (Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * Get slowest queries from performance schema.
     */
    private function getSlowQueries(string $connection): array
    {
        $driver = config("database.connections.{$connection}.driver");

        try {
            if ($driver === 'mysql') {
                // Check if performance_schema is enabled
                $check = DB::connection($connection)
                    ->select("SHOW VARIABLES LIKE 'performance_schema'");

                if (empty($check) || $check[0]->Value !== 'ON') {
                    return [];
                }

                $results = DB::connection($connection)
                    ->select("SELECT
                        SUBSTRING(DIGEST_TEXT, 1, 100) AS query_sample,
                        COUNT_STAR AS exec_count,
                        ROUND(AVG_TIMER_WAIT / 1000000000000, 3) AS avg_time_sec,
                        ROUND(MAX_TIMER_WAIT / 1000000000000, 3) AS max_time_sec,
                        ROUND(SUM_TIMER_WAIT / 1000000000000, 2) AS total_time_sec
                    FROM performance_schema.events_statements_summary_by_digest
                    WHERE SCHEMA_NAME = ?
                    AND DIGEST_TEXT NOT LIKE '%performance_schema%'
                    AND DIGEST_TEXT NOT LIKE '%information_schema%'
                    ORDER BY AVG_TIMER_WAIT DESC
                    LIMIT 10", [config("database.connections.{$connection}.database")]);

                return array_map(fn ($row) => [
                    'query' => $row->query_sample,
                    'exec_count' => (int) $row->exec_count,
                    'avg_time' => (float) $row->avg_time_sec,
                    'max_time' => (float) $row->max_time_sec,
                    'total_time' => (float) $row->total_time_sec,
                ], $results);
            }
        } catch (Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * Get queries that don't use indexes.
     */
    private function getQueriesWithoutIndexes(string $connection): array
    {
        $driver = config("database.connections.{$connection}.driver");

        try {
            if ($driver === 'mysql') {
                // Check if performance_schema is enabled
                $check = DB::connection($connection)
                    ->select("SHOW VARIABLES LIKE 'performance_schema'");

                if (empty($check) || $check[0]->Value !== 'ON') {
                    return [];
                }

                $results = DB::connection($connection)
                    ->select("SELECT
                        SUBSTRING(DIGEST_TEXT, 1, 100) AS query_sample,
                        COUNT_STAR AS exec_count,
                        SUM_NO_INDEX_USED AS no_index_count,
                        SUM_NO_GOOD_INDEX_USED AS bad_index_count,
                        ROUND(AVG_TIMER_WAIT / 1000000000000, 3) AS avg_time_sec
                    FROM performance_schema.events_statements_summary_by_digest
                    WHERE SCHEMA_NAME = ?
                    AND (SUM_NO_INDEX_USED > 0 OR SUM_NO_GOOD_INDEX_USED > 0)
                    AND DIGEST_TEXT NOT LIKE '%performance_schema%'
                    AND DIGEST_TEXT NOT LIKE '%information_schema%'
                    ORDER BY SUM_NO_INDEX_USED DESC, COUNT_STAR DESC
                    LIMIT 10", [config("database.connections.{$connection}.database")]);

                return array_map(fn ($row) => [
                    'query' => $row->query_sample,
                    'exec_count' => (int) $row->exec_count,
                    'no_index_count' => (int) $row->no_index_count,
                    'bad_index_count' => (int) $row->bad_index_count,
                    'avg_time' => (float) $row->avg_time_sec,
                ], $results);
            }
        } catch (Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * Show system status page.
     */
    public function systemStatus(): View
    {
        $this->authorize('admin-only');

        $status = [
            'server' => $this->getServerHealth(),
            'php' => $this->getPhpInfo(),
            'laravel' => $this->getLaravelInfo(),
            'services' => $this->getServicesStatus(),
            'queue' => $this->getQueueStatus(),
        ];

        return view('admin.system-status', compact('status'));
    }

    /**
     * Get server health metrics.
     */
    private function getServerHealth(): array
    {
        try {
            $load = sys_getloadavg();

            // Memory info
            $memTotal = 0;
            $memFree = 0;

            if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/meminfo')) {
                $meminfo = file_get_contents('/proc/meminfo');
                preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
                preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $free);
                $memTotal = isset($total[1]) ? (int) $total[1] : 0;
                $memFree = isset($free[1]) ? (int) $free[1] : 0;
            }

            // Uptime
            $uptime = 0;
            if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
                $uptimeData = file_get_contents('/proc/uptime');
                $uptime = (int) explode(' ', $uptimeData)[0];
            }

            return [
                'load_average' => [
                    '1min' => round($load[0], 2),
                    '5min' => round($load[1], 2),
                    '15min' => round($load[2], 2),
                ],
                'memory' => [
                    'total_gb' => round($memTotal / 1024 / 1024, 2),
                    'free_gb' => round($memFree / 1024 / 1024, 2),
                    'used_gb' => round(($memTotal - $memFree) / 1024 / 1024, 2),
                    'used_percent' => $memTotal > 0 ? round((($memTotal - $memFree) / $memTotal) * 100, 1) : 0,
                ],
                'uptime_days' => round($uptime / 86400, 1),
            ];
        } catch (Exception $e) {
            return [
                'load_average' => ['1min' => 0, '5min' => 0, '15min' => 0],
                'memory' => ['total_gb' => 0, 'free_gb' => 0, 'used_gb' => 0, 'used_percent' => 0],
                'uptime_days' => 0,
                'error' => ErrorHelper::getSafeError($e),
            ];
        }
    }

    /**
     * Get PHP information.
     */
    private function getPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
        ];
    }

    /**
     * Get Laravel application information.
     */
    private function getLaravelInfo(): array
    {
        return [
            'version' => app()->version(),
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'queue_driver' => config('queue.default'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
        ];
    }

    /**
     * Get services status.
     */
    private function getServicesStatus(): array
    {
        $services = [];

        // MySQL
        try {
            DB::connection()->getPdo();
            $services['mysql'] = ['status' => 'up', 'message' => 'Connected'];
        } catch (Exception $e) {
            $services['mysql'] = ['status' => 'down', 'message' => ErrorHelper::getSafeMessage($e, 'Connection failed')];
        }

        // Redis (if configured)
        try {
            if (config('cache.default') === 'redis' || config('queue.default') === 'redis') {
                Cache::store('redis')->put('health_check', 'ok', 10);
                $services['redis'] = ['status' => 'up', 'message' => 'Connected'];
            }
        } catch (Exception $e) {
            $services['redis'] = ['status' => 'down', 'message' => ErrorHelper::getSafeMessage($e, 'Connection failed')];
        }

        // Nuxt Frontend
        try {
            $frontendUrl = config('app.client_url', 'http://localhost:3000');
            $response = Http::timeout(3)->get($frontendUrl);
            $services['nuxt'] = ['status' => $response->successful() ? 'up' : 'down', 'message' => 'HTTP ' . $response->status()];
        } catch (Exception $e) {
            $services['nuxt'] = ['status' => 'down', 'message' => 'Cannot connect'];
        }

        // Mbin (if configured)
        try {
            $mbinHost = config('services.mbin.host');
            if ($mbinHost) {
                DB::connection('mbin')->getPdo();
                $services['mbin'] = ['status' => 'up', 'message' => 'Connected to ' . $mbinHost];
            }
        } catch (Exception $e) {
            $services['mbin'] = ['status' => 'down', 'message' => 'Cannot connect'];
        }

        return $services;
    }

    /**
     * Get queue status.
     */
    private function getQueueStatus(): array
    {
        try {
            $failedJobsCount = DB::table('failed_jobs')->count();

            // Get last 10 failed jobs with details
            $failedJobsDetails = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn ($job) => [
                    'id' => $job->id,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'exception' => substr($job->exception, 0, 200), // First 200 chars
                    'failed_at' => $job->failed_at,
                ]);

            return [
                'driver' => config('queue.default'),
                'failed_jobs' => $failedJobsCount,
                'failed_jobs_details' => $failedJobsDetails,
            ];
        } catch (Exception $e) {
            return [
                'driver' => config('queue.default'),
                'failed_jobs' => 0,
                'failed_jobs_details' => [],
                'error' => ErrorHelper::getSafeError($e),
            ];
        }
    }

    /**
     * Show karma and achievements configuration page.
     */
    public function karmaConfiguration(): View
    {
        $this->authorize('access-admin');

        // Group achievements by thematic category based on requirements
        $allAchievements = \App\Models\Achievement::orderBy('karma_bonus', 'desc')->get();

        $achievements = $allAchievements->groupBy(function ($achievement) {
            if (isset($achievement->requirements['type'])) {
                return match ($achievement->requirements['type']) {
                    'relationships' => 'Relaciones entre Posts',
                    'posts' => 'Publicaciones',
                    'comments' => 'Comentarios',
                    'votes' => 'Votaciones',
                    default => 'Otros',
                };
            }

            // Fallback to type if no requirements type
            return match ($achievement->type) {
                'special' => 'Logros Especiales',
                'action' => 'Acciones',
                'milestone' => 'Hitos',
                default => 'Otros',
            };
        });

        $karmaLevels = \App\Models\KarmaLevel::orderBy('required_karma')->get();

        $totalKarmaAvailable = \App\Models\Achievement::sum('karma_bonus');

        return view('admin.karma-configuration', compact('achievements', 'karmaLevels', 'totalKarmaAvailable'));
    }

    /**
     * Get system health summary for dashboard.
     */
    public function getSystemHealthSummary(): array
    {
        $server = $this->getServerHealth();
        $services = $this->getServicesStatus();

        $servicesUp = count(array_filter($services, fn ($s) => $s['status'] === 'up'));
        $servicesTotal = count($services);

        return [
            'server_load' => $server['load_average']['1min'],
            'memory_used_percent' => $server['memory']['used_percent'],
            'services_up' => $servicesUp,
            'services_total' => $servicesTotal,
            'all_services_up' => $servicesUp === $servicesTotal,
        ];
    }

    /**
     * Clear various types of cache.
     */
    public function clearCache(Request $request, string $type): JsonResponse
    {
        $this->authorize('admin-only');

        try {
            $message = '';

            switch ($type) {
                case 'config':
                    Artisan::call('config:clear');
                    $message = 'Configuration cache cleared successfully';
                    break;

                case 'cache':
                    Artisan::call('cache:clear');
                    $message = 'Application cache cleared successfully';
                    break;

                case 'view':
                    Artisan::call('view:clear');
                    $message = 'Compiled views cleared successfully';
                    break;

                case 'route':
                    Artisan::call('route:clear');
                    $message = 'Route cache cleared successfully';
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid cache type',
                    ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.admin.cache_clear_error'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    /**
     * Show the spam detection dashboard.
     */
    public function spamDetection(Request $request): View
    {
        $minScore = $request->integer('min_score', 50);
        $hours = $request->integer('hours', 24);

        $detector = app(\App\Services\DuplicateContentDetector::class);

        // Get users with cached spam scores
        $processedUserIds = Cache::get('spam_score_users', []);
        $suspiciousUsers = [];

        foreach ($processedUserIds as $userId) {
            $spamScore = Cache::get("spam_score:{$userId}");

            if ($spamScore && $spamScore['score'] >= $minScore) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    $suspiciousUsers[] = [
                        'user' => $user,
                        'spam_score' => $spamScore['score'],
                        'risk_level' => $spamScore['risk_level'],
                        'reasons' => $spamScore['reasons'],
                        'recent_posts' => $user->posts()->where('created_at', '>=', now()->subHours($hours))->count(),
                        'recent_comments' => $user->comments()->where('created_at', '>=', now()->subHours($hours))->count(),
                    ];
                }
            }
        }

        // Sort by spam score descending
        usort($suspiciousUsers, fn ($a, $b) => $b['spam_score'] <=> $a['spam_score']);

        // Get overview stats
        $stats = [
            'total_processed' => count($processedUserIds),
            'suspicious_count' => count($suspiciousUsers),
            'high_risk_count' => count(array_filter($suspiciousUsers, fn ($u) => $u['spam_score'] >= 70)),
            'last_scan' => Cache::get('spam_score_last_scan', now()),
        ];

        return view('admin.spam-detection', compact('suspiciousUsers', 'stats', 'minScore', 'hours'));
    }

    /**
     * Show spam detection logs.
     */
    public function spamLogs(Request $request): View
    {
        $query = \App\Models\SpamDetection::with(['user', 'reviewer'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('reviewed')) {
            $query->where('reviewed', $request->boolean('reviewed'));
        }

        if ($request->filled('detection_type')) {
            $query->where('detection_type', $request->input('detection_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('days')) {
            $days = $request->integer('days', 7);
            $query->where('created_at', '>=', now()->subDays($days));
        }

        $detections = $query->paginate(50);

        // Stats
        $stats = [
            'total' => \App\Models\SpamDetection::count(),
            'pending_review' => \App\Models\SpamDetection::where('reviewed', false)->count(),
            'today' => \App\Models\SpamDetection::whereDate('created_at', today())->count(),
            'this_week' => \App\Models\SpamDetection::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return view('admin.spam-logs', compact('detections', 'stats'));
    }

    /**
     * Mark spam detection as reviewed.
     */
    public function reviewSpamDetection(Request $request, int $id): JsonResponse
    {
        $detection = \App\Models\SpamDetection::findOrFail($id);

        $detection->update([
            'reviewed' => true,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'action_taken' => $request->input('action', 'ignored'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detection marked as reviewed',
        ]);
    }

    /**
     * Show spam configuration page.
     */
    public function spamConfiguration(): View
    {
        $settings = \App\Models\SpamSetting::orderBy('key')->get()->groupBy(function ($setting) {
            // Group settings by category based on prefix
            if (str_starts_with($setting->key, 'duplicate_')) {
                return 'Duplicate Detection';
            }
            if (str_starts_with($setting->key, 'spam_score_')) {
                return 'Spam Score';
            }
            if (str_starts_with($setting->key, 'rapid_fire_')) {
                return 'Rapid Fire Detection';
            }
            if (str_starts_with($setting->key, 'auto_')) {
                return 'Automatic Actions';
            }

            return 'Other';
        });

        return view('admin.spam-configuration', compact('settings'));
    }

    /**
     * Update spam configuration.
     */
    public function updateSpamConfiguration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        foreach ($validated['settings'] as $setting) {
            \App\Models\SpamSetting::setValue($setting['key'], $setting['value']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuration updated successfully',
        ]);
    }
}
