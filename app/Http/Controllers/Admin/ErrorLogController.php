<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use const PHP_INT_MAX;
use const PREG_SET_ORDER;

use App\Http\Controllers\Controller;
use DateTime;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use SplFileObject;
use Symfony\Component\Process\Process;

final class ErrorLogController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get common paths for system logs.
     * Uses app name from config for site-specific log paths.
     */
    private function getSystemLogPaths(): array
    {
        $appName = strtolower(config('app.name', 'app'));

        return [
            'nginx_error' => [
                '/var/log/nginx/error.log',
                "/var/log/nginx/{$appName}-error.log",
                '/var/log/nginx/app-error.log',
            ],
            'nginx_access' => [
                '/var/log/nginx/access.log',
                "/var/log/nginx/{$appName}-access.log",
            ],
            'php_fpm' => [
                '/var/log/php8.4-fpm.log',
                '/var/log/php8.3-fpm.log',
                '/var/log/php8.2-fpm.log',
                '/var/log/php-fpm/error.log',
                '/var/log/php-fpm/www-error.log',
                '/var/log/php/php8.2-fpm.log',
                '/var/log/php-fpm.log',
            ],
            'syslog' => [
                '/var/log/syslog',
                '/var/log/messages',
            ],
        ];
    }

    /**
     * Show the error logs page.
     */
    public function index(Request $request)
    {
        $this->authorize('admin-only');

        $source = $request->input('source', 'laravel');
        $limit = (int) $request->input('limit', 50);

        $errors = [];
        $totalSize = 0;
        $lastModified = null;
        $logFilePath = null;
        $canRead = true;
        $errorMessage = null;

        if ($source === 'laravel') {
            $logFilePath = storage_path('logs/laravel.log');
            if (File::exists($logFilePath)) {
                $totalSize = File::size($logFilePath);
                $lastModified = File::lastModified($logFilePath);
                $errors = $this->parseLaravelLog($logFilePath, $limit);
            }
        } else {
            // System logs
            $logFilePath = $this->findSystemLog($source);
            if ($logFilePath !== null && File::exists($logFilePath)) {
                if (is_readable($logFilePath)) {
                    $totalSize = File::size($logFilePath);
                    $lastModified = File::lastModified($logFilePath);
                    $errors = $this->parseSystemLog($logFilePath, $source, $limit);
                } else {
                    $canRead = false;
                    $errorMessage = "Cannot read log file: {$logFilePath}. Check file permissions.";
                }
            } else {
                $canRead = false;
                $errorMessage = "Log file not found for source: {$source}";
            }
        }

        // Get available log files from Laravel
        $logFiles = $this->getAvailableLogFiles();

        // Get system log status
        $systemLogs = $this->getSystemLogStatus();

        return view('admin.error-logs', [
            'errors' => $errors,
            'totalSize' => $totalSize,
            'lastModified' => $lastModified,
            'logFiles' => $logFiles,
            'systemLogs' => $systemLogs,
            'currentLimit' => $limit,
            'currentSource' => $source,
            'logFilePath' => $logFilePath,
            'canRead' => $canRead,
            'errorMessage' => $errorMessage,
        ]);
    }

    /**
     * Find the first existing system log file for a given source.
     */
    private function findSystemLog(string $source): ?string
    {
        $paths = $this->getSystemLogPaths()[$source] ?? [];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Get status of all system logs.
     */
    private function getSystemLogStatus(): array
    {
        $status = [];

        foreach ($this->getSystemLogPaths() as $source => $paths) {
            $found = false;
            $readable = false;
            $path = null;
            $size = 0;

            foreach ($paths as $p) {
                if (File::exists($p)) {
                    $found = true;
                    $path = $p;
                    $readable = is_readable($p);
                    if ($readable) {
                        $size = File::size($p);
                    }
                    break;
                }
            }

            $status[$source] = [
                'name' => $this->getSourceDisplayName($source),
                'found' => $found,
                'readable' => $readable,
                'path' => $path,
                'size' => $size,
                'icon' => $this->getSourceIcon($source),
            ];
        }

        return $status;
    }

    /**
     * Get display name for a log source.
     */
    private function getSourceDisplayName(string $source): string
    {
        return match ($source) {
            'laravel' => 'Laravel',
            'nginx_error' => 'Nginx Error',
            'nginx_access' => 'Nginx Access',
            'php_fpm' => 'PHP-FPM',
            'syslog' => 'System Log',
            default => ucfirst($source),
        };
    }

    /**
     * Get icon for a log source.
     */
    private function getSourceIcon(string $source): string
    {
        return match ($source) {
            'laravel' => 'fa-laravel',
            'nginx_error', 'nginx_access' => 'fa-server',
            'php_fpm' => 'fa-php',
            'syslog' => 'fa-linux',
            default => 'fa-file-alt',
        };
    }

    /**
     * Parse the Laravel log file and extract errors.
     */
    private function parseLaravelLog(string $filePath, int $limit = 50): array
    {
        $errors = [];
        $content = File::get($filePath);

        // Laravel log format: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
        $pattern = '/\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s+(.+?)(?=\[\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\]|$)/s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $errorLevels = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

        foreach (array_reverse($matches) as $match) {
            $level = strtoupper($match[3]);

            if (in_array($level, $errorLevels, true)) {
                $message = trim($match[4]);
                $lines = explode("\n", $message);
                $mainMessage = $lines[0];
                $hasStackTrace = strpos($message, '#0 ') !== false || strpos($message, 'Stack trace:') !== false;
                $issue = $this->detectLaravelIssue($message);

                $errors[] = [
                    'timestamp' => $match[1],
                    'environment' => $match[2],
                    'level' => $level,
                    'message' => $mainMessage,
                    'full_message' => $message,
                    'has_stack_trace' => $hasStackTrace,
                    'source' => 'laravel',
                    'attack_type' => $issue['type'] ?? null,
                    'attack_description' => $issue['description'] ?? null,
                    'attack_severity' => $issue['severity'] ?? null,
                ];

                if (count($errors) >= $limit) {
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Detect issues in Laravel logs.
     */
    private function detectLaravelIssue(string $message): ?array
    {
        $patterns = [
            // Database issues
            [
                'pattern' => '/SQLSTATE.*Connection refused|MySQL server has gone away|Too many connections/i',
                'type' => 'database_down',
                'description' => 'Database Down - Cannot connect to database server',
                'severity' => 'critical',
            ],
            [
                'pattern' => '/SQLSTATE.*Deadlock found/i',
                'type' => 'deadlock',
                'description' => 'Database Deadlock - Transaction conflict detected',
                'severity' => 'high',
            ],
            [
                'pattern' => '/SQLSTATE.*Duplicate entry/i',
                'type' => 'duplicate_entry',
                'description' => 'Duplicate Entry - Unique constraint violation',
                'severity' => 'medium',
            ],
            // Authentication/Authorization
            [
                'pattern' => '/Unauthenticated|AuthenticationException/i',
                'type' => 'auth_error',
                'description' => 'Authentication Error - User not logged in',
                'severity' => 'low',
            ],
            [
                'pattern' => '/AuthorizationException|This action is unauthorized/i',
                'type' => 'authorization_error',
                'description' => 'Authorization Error - User lacks permission',
                'severity' => 'medium',
            ],
            // Validation
            [
                'pattern' => '/ValidationException/i',
                'type' => 'validation_error',
                'description' => 'Validation Error - Invalid input data',
                'severity' => 'low',
            ],
            // Rate limiting
            [
                'pattern' => '/ThrottleRequestsException|Too Many Attempts|429/i',
                'type' => 'rate_limit',
                'description' => 'Rate Limited - Too many requests',
                'severity' => 'info',
            ],
            // Model errors
            [
                'pattern' => '/ModelNotFoundException|No query results for model/i',
                'type' => 'model_not_found',
                'description' => 'Model Not Found - Record does not exist',
                'severity' => 'low',
            ],
            // Queue/Job errors
            [
                'pattern' => '/MaxAttemptsExceededException|Job.*failed/i',
                'type' => 'job_failed',
                'description' => 'Job Failed - Background job failed after retries',
                'severity' => 'high',
            ],
            // Mail errors
            [
                'pattern' => '/Swift_TransportException|Failed to send email|Mail.*failed/i',
                'type' => 'mail_error',
                'description' => 'Mail Error - Failed to send email',
                'severity' => 'high',
            ],
            // File/Storage errors
            [
                'pattern' => '/League\\\\Flysystem|Storage.*failed|Unable to write/i',
                'type' => 'storage_error',
                'description' => 'Storage Error - File system or S3 problem',
                'severity' => 'high',
            ],
            // Redis/Cache errors
            [
                'pattern' => '/RedisException|Cache.*failed|Connection refused.*6379/i',
                'type' => 'cache_error',
                'description' => 'Cache Error - Redis or cache connection problem',
                'severity' => 'high',
            ],
            // Memory issues
            [
                'pattern' => '/Allowed memory size.*exhausted/i',
                'type' => 'memory_exhausted',
                'description' => 'Memory Exhausted - PHP ran out of memory',
                'severity' => 'critical',
            ],
            // Timeout
            [
                'pattern' => '/Maximum execution time|cURL error 28|Operation timed out/i',
                'type' => 'timeout',
                'description' => 'Timeout - Operation took too long',
                'severity' => 'high',
            ],
            // External API errors
            [
                'pattern' => '/cURL error|GuzzleHttp|RequestException|Connection reset by peer/i',
                'type' => 'external_api_error',
                'description' => 'External API Error - Third-party service problem',
                'severity' => 'medium',
            ],
            // Security related
            [
                'pattern' => '/CSRF token mismatch|TokenMismatchException/i',
                'type' => 'csrf_error',
                'description' => 'CSRF Error - Token mismatch (possible attack)',
                'severity' => 'medium',
            ],
            [
                'pattern' => '/DecryptException|The MAC is invalid/i',
                'type' => 'encryption_error',
                'description' => 'Encryption Error - Tampered or invalid encrypted data',
                'severity' => 'high',
            ],
        ];

        foreach ($patterns as $p) {
            if (preg_match($p['pattern'], $message)) {
                return [
                    'type' => $p['type'],
                    'description' => $p['description'],
                    'severity' => $p['severity'],
                ];
            }
        }

        return null;
    }

    /**
     * Parse system log files (nginx, php-fpm, syslog).
     */
    private function parseSystemLog(string $filePath, string $source, int $limit = 50): array
    {
        $errors = [];

        // Read last N lines efficiently using tail
        $lines = $this->readLastLines($filePath, $limit * 10); // Read more lines, then filter

        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parsed = match ($source) {
                'nginx_error' => $this->parseNginxErrorLine($line),
                'nginx_access' => $this->parseNginxAccessLine($line),
                'php_fpm' => $this->parsePhpFpmLine($line),
                'syslog' => $this->parseSyslogLine($line),
                default => null,
            };

            if ($parsed !== null) {
                $parsed['source'] = $source;
                $errors[] = $parsed;

                if (count($errors) >= $limit) {
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Read last N lines from a file efficiently.
     */
    private function readLastLines(string $filePath, int $lines): array
    {
        $result = [];

        try {
            // Use tail command via Process for efficiency on large files
            $process = new Process(['tail', '-n', (string) $lines, $filePath]);
            $process->run();

            if ($process->isSuccessful() && $process->getOutput() !== '') {
                $result = explode("\n", $process->getOutput());
            } else {
                // Fallback to PHP reading
                $result = $this->readLastLinesWithPhp($filePath, $lines);
            }
        } catch (Exception $e) {
            // Fallback to PHP reading
            $result = $this->readLastLinesWithPhp($filePath, $lines);
        }

        return array_filter($result, static fn ($line) => $line !== '' && $line !== null);
    }

    /**
     * Fallback method to read last lines using PHP.
     */
    private function readLastLinesWithPhp(string $filePath, int $lines): array
    {
        $result = [];
        $file = new SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);

        while (! $file->eof()) {
            $result[] = $file->fgets();
        }

        return $result;
    }

    /**
     * Parse Nginx error log line.
     * Format: YYYY/MM/DD HH:MM:SS [level] PID#TID: *CID message.
     */
    private function parseNginxErrorLine(string $line): ?array
    {
        // Nginx error format: 2024/01/15 10:30:45 [error] 1234#5678: *90 message
        $pattern = '/^(\d{4}\/\d{2}\/\d{2}\s\d{2}:\d{2}:\d{2})\s+\[(\w+)\]\s+(\d+#\d+):\s*\*?\d*\s*(.+)$/';

        if (preg_match($pattern, $line, $matches)) {
            $level = strtoupper($matches[2]);

            // Only include errors and warnings
            if (in_array($level, ['ERROR', 'CRIT', 'ALERT', 'EMERG', 'WARN'], true)) {
                $message = $matches[4];
                $issue = $this->detectNginxIssue($message);

                return [
                    'timestamp' => str_replace('/', '-', $matches[1]),
                    'level' => $level === 'CRIT' ? 'CRITICAL' : $level,
                    'environment' => 'nginx',
                    'message' => $message,
                    'full_message' => $line,
                    'has_stack_trace' => false,
                    'attack_type' => $issue['type'] ?? null,
                    'attack_description' => $issue['description'] ?? null,
                    'attack_severity' => $issue['severity'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Detect issues in nginx error logs.
     */
    private function detectNginxIssue(string $message): ?array
    {
        $patterns = [
            [
                'pattern' => '/SSL_do_handshake|SSL_read|ssl_stapling/i',
                'type' => 'ssl_error',
                'description' => 'SSL/TLS Error - Certificate or handshake problem',
                'severity' => 'medium',
            ],
            [
                'pattern' => '/upstream timed out|upstream prematurely closed/i',
                'type' => 'upstream_timeout',
                'description' => 'Upstream Timeout - Backend server not responding',
                'severity' => 'high',
            ],
            [
                'pattern' => '/connect\(\) failed.*Connection refused/i',
                'type' => 'backend_down',
                'description' => 'Backend Down - Cannot connect to application server',
                'severity' => 'critical',
            ],
            [
                'pattern' => '/no live upstreams/i',
                'type' => 'no_upstream',
                'description' => 'No Upstreams - All backend servers are down',
                'severity' => 'critical',
            ],
            [
                'pattern' => '/client intended to send too large body/i',
                'type' => 'body_too_large',
                'description' => 'Request Too Large - Client sent oversized request',
                'severity' => 'low',
            ],
            [
                'pattern' => '/access forbidden|directory index.*forbidden/i',
                'type' => 'forbidden',
                'description' => 'Access Forbidden - Permission denied',
                'severity' => 'info',
            ],
            [
                'pattern' => '/open\(\).*failed.*No such file/i',
                'type' => 'file_not_found',
                'description' => 'File Not Found - Requested file does not exist',
                'severity' => 'info',
            ],
            [
                'pattern' => '/worker_connections are not enough/i',
                'type' => 'connection_limit',
                'description' => 'Connection Limit - Too many concurrent connections',
                'severity' => 'high',
            ],
            [
                'pattern' => '/could not build server_names_hash/i',
                'type' => 'config_error',
                'description' => 'Config Error - Nginx configuration problem',
                'severity' => 'high',
            ],
        ];

        foreach ($patterns as $p) {
            if (preg_match($p['pattern'], $message)) {
                return [
                    'type' => $p['type'],
                    'description' => $p['description'],
                    'severity' => $p['severity'],
                ];
            }
        }

        return null;
    }

    /**
     * Parse Nginx access log line for errors (4xx, 5xx).
     * Combined log format.
     */
    private function parseNginxAccessLine(string $line): ?array
    {
        // Combined format: IP - - [timestamp] "METHOD URL PROTO" STATUS SIZE "REFERER" "UA"
        $pattern = '/^(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([^"]+)"\s+(\d{3})\s+/';

        if (preg_match($pattern, $line, $matches)) {
            $statusCode = (int) $matches[4];

            // Only include 4xx and 5xx errors
            if ($statusCode >= 400) {
                $level = $statusCode >= 500 ? 'ERROR' : 'WARNING';
                $request = $matches[3];
                $ip = $matches[1];

                // Detect attack patterns
                $attack = $this->detectAttackPattern($request);

                return [
                    'timestamp' => $this->parseNginxTimestamp($matches[2]),
                    'level' => $level,
                    'environment' => 'nginx',
                    'message' => "HTTP {$statusCode}: {$request} from {$ip}",
                    'full_message' => $line,
                    'has_stack_trace' => false,
                    'attack_type' => $attack['type'] ?? null,
                    'attack_description' => $attack['description'] ?? null,
                    'attack_severity' => $attack['severity'] ?? null,
                    'ip' => $ip,
                ];
            }
        }

        return null;
    }

    /**
     * Detect attack patterns in HTTP requests.
     */
    private function detectAttackPattern(string $request): ?array
    {
        $patterns = [
            // Path traversal attacks
            [
                'pattern' => '/\.\.\/|%2e%2e|%%32%65|\.%2e|%2e\./i',
                'type' => 'path_traversal',
                'description' => 'Path Traversal - Attempt to access files outside web root',
                'severity' => 'high',
            ],
            // Shell/Command injection
            [
                'pattern' => '/\/bin\/sh|\/bin\/bash|cmd\.exe|powershell|wget\s|curl\s|;sh\s|;bash/i',
                'type' => 'rce',
                'description' => 'Remote Code Execution - Attempt to execute system commands',
                'severity' => 'critical',
            ],
            // Proxy abuse (CONNECT method)
            [
                'pattern' => '/^CONNECT\s/i',
                'type' => 'proxy_abuse',
                'description' => 'Proxy Abuse - Attempt to use server as open proxy',
                'severity' => 'medium',
            ],
            // SQL Injection
            [
                'pattern' => '/(\bunion\b.*\bselect\b|\bor\b\s+1\s*=\s*1|\band\b\s+1\s*=\s*1|\'.*--|\bDROP\b.*\bTABLE\b)/i',
                'type' => 'sql_injection',
                'description' => 'SQL Injection - Attempt to inject SQL commands',
                'severity' => 'critical',
            ],
            // XSS attempts
            [
                'pattern' => '/<script|javascript:|onerror\s*=|onload\s*=/i',
                'type' => 'xss',
                'description' => 'XSS - Cross-Site Scripting attempt',
                'severity' => 'high',
            ],
            // CGI-bin exploits
            [
                'pattern' => '/\/cgi-bin\//i',
                'type' => 'cgi_exploit',
                'description' => 'CGI Exploit - Attempt to exploit CGI vulnerabilities',
                'severity' => 'high',
            ],
            // PHP exploits
            [
                'pattern' => '/\.php\?.*=.*http|eval\(|base64_decode|phpinfo|php:\/\/input/i',
                'type' => 'php_exploit',
                'description' => 'PHP Exploit - Attempt to exploit PHP vulnerabilities',
                'severity' => 'high',
            ],
            // WordPress/CMS scanning
            [
                'pattern' => '/wp-admin|wp-login|wp-content|xmlrpc\.php|wp-config/i',
                'type' => 'cms_scan',
                'description' => 'CMS Scan - WordPress/CMS vulnerability scanning',
                'severity' => 'low',
            ],
            // Admin panel scanning
            [
                'pattern' => '/\/admin\/|\/administrator\/|\/phpmyadmin|\/pma\/|\/mysql/i',
                'type' => 'admin_scan',
                'description' => 'Admin Scan - Looking for admin panels',
                'severity' => 'low',
            ],
            // Malformed/Binary requests (SSL on non-SSL port, etc)
            [
                'pattern' => '/^\\\\x[0-9a-f]{2}/i',
                'type' => 'malformed',
                'description' => 'Malformed Request - Binary/SSL data on wrong port',
                'severity' => 'info',
            ],
            // Bot/Scanner identification strings
            [
                'pattern' => '/MGLNDD_|masscan|zgrab|nmap/i',
                'type' => 'scanner',
                'description' => 'Scanner - Automated vulnerability scanner',
                'severity' => 'info',
            ],
            // Sensitive file access
            [
                'pattern' => '/\.env|\.git\/|\.htaccess|\.htpasswd|web\.config|\.aws\/|\.ssh\//i',
                'type' => 'sensitive_file',
                'description' => 'Sensitive File Access - Attempt to read config/secret files',
                'severity' => 'high',
            ],
            // Log4j/JNDI
            [
                'pattern' => '/\$\{jndi:|log4j|%24%7bjndi/i',
                'type' => 'log4j',
                'description' => 'Log4j/JNDI - Log4Shell exploit attempt',
                'severity' => 'critical',
            ],
        ];

        foreach ($patterns as $p) {
            if (preg_match($p['pattern'], $request)) {
                return [
                    'type' => $p['type'],
                    'description' => $p['description'],
                    'severity' => $p['severity'],
                ];
            }
        }

        return null;
    }

    /**
     * Parse Nginx timestamp to standard format.
     */
    private function parseNginxTimestamp(string $timestamp): string
    {
        // Format: 15/Jan/2024:10:30:45 +0000
        try {
            $dt = DateTime::createFromFormat('d/M/Y:H:i:s O', $timestamp);
            if ($dt) {
                return $dt->format('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
            // Ignore
        }

        return $timestamp;
    }

    /**
     * Parse PHP-FPM log line.
     * Format: [DD-Mon-YYYY HH:MM:SS] LEVEL: message.
     */
    private function parsePhpFpmLine(string $line): ?array
    {
        // PHP-FPM format: [15-Jan-2024 10:30:45] WARNING: [pool www] message
        // Or: [15-Jan-2024 10:30:45 UTC] PHP Fatal error: message
        $pattern = '/^\[([^\]]+)\]\s+(?:PHP\s+)?(\w+)(?:\s+error)?:\s*(.+)$/i';

        if (preg_match($pattern, $line, $matches)) {
            $level = strtoupper($matches[2]);

            // Map PHP error levels
            $level = match ($level) {
                'FATAL' => 'CRITICAL',
                'PARSE' => 'ERROR',
                'NOTICE' => 'WARNING',
                default => $level,
            };

            if (in_array($level, ['ERROR', 'CRITICAL', 'WARNING', 'ALERT'], true)) {
                $message = trim($matches[3]);
                $issue = $this->detectPhpIssue($message);

                return [
                    'timestamp' => $this->parsePhpTimestamp($matches[1]),
                    'level' => $level,
                    'environment' => 'php-fpm',
                    'message' => $message,
                    'full_message' => $line,
                    'has_stack_trace' => strpos($line, 'Stack trace') !== false,
                    'attack_type' => $issue['type'] ?? null,
                    'attack_description' => $issue['description'] ?? null,
                    'attack_severity' => $issue['severity'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Detect issues in PHP-FPM logs.
     */
    private function detectPhpIssue(string $message): ?array
    {
        $patterns = [
            [
                'pattern' => '/memory exhausted|Allowed memory size.*exhausted/i',
                'type' => 'memory_exhausted',
                'description' => 'Memory Exhausted - PHP ran out of memory',
                'severity' => 'critical',
            ],
            [
                'pattern' => '/Maximum execution time.*exceeded/i',
                'type' => 'timeout',
                'description' => 'Execution Timeout - Script took too long',
                'severity' => 'high',
            ],
            [
                'pattern' => '/Call to undefined function|Call to undefined method/i',
                'type' => 'undefined_function',
                'description' => 'Undefined Function/Method - Code error',
                'severity' => 'high',
            ],
            [
                'pattern' => '/Class.*not found/i',
                'type' => 'class_not_found',
                'description' => 'Class Not Found - Missing class or autoload issue',
                'severity' => 'high',
            ],
            [
                'pattern' => '/SQLSTATE|MySQL server has gone away|Connection refused.*mysql/i',
                'type' => 'database_error',
                'description' => 'Database Error - Database connection or query problem',
                'severity' => 'critical',
            ],
            [
                'pattern' => '/Permission denied|failed to open stream.*Permission/i',
                'type' => 'permission_denied',
                'description' => 'Permission Denied - File system permission issue',
                'severity' => 'high',
            ],
            [
                'pattern' => '/No such file or directory/i',
                'type' => 'file_not_found',
                'description' => 'File Not Found - Missing file or wrong path',
                'severity' => 'medium',
            ],
            [
                'pattern' => '/Undefined variable|Undefined index|Undefined array key/i',
                'type' => 'undefined_var',
                'description' => 'Undefined Variable - Accessing non-existent variable',
                'severity' => 'low',
            ],
            [
                'pattern' => '/syntax error|Parse error/i',
                'type' => 'syntax_error',
                'description' => 'Syntax Error - PHP code syntax problem',
                'severity' => 'critical',
            ],
            [
                'pattern' => '/failed to open stream.*Too many open files/i',
                'type' => 'too_many_files',
                'description' => 'Too Many Open Files - File descriptor limit reached',
                'severity' => 'critical',
            ],
            [
                'pattern' => '/\[pool.*\] child \d+ exited on signal/i',
                'type' => 'worker_crash',
                'description' => 'Worker Crash - PHP-FPM worker process crashed',
                'severity' => 'high',
            ],
            [
                'pattern' => '/server reached pm\.max_children/i',
                'type' => 'max_children',
                'description' => 'Max Children Reached - PHP-FPM pool exhausted',
                'severity' => 'critical',
            ],
        ];

        foreach ($patterns as $p) {
            if (preg_match($p['pattern'], $message)) {
                return [
                    'type' => $p['type'],
                    'description' => $p['description'],
                    'severity' => $p['severity'],
                ];
            }
        }

        return null;
    }

    /**
     * Parse PHP timestamp to standard format.
     */
    private function parsePhpTimestamp(string $timestamp): string
    {
        // Format: 15-Jan-2024 10:30:45 or 15-Jan-2024 10:30:45 UTC
        $timestamp = preg_replace('/\s+(UTC|GMT|[A-Z]{3,4})$/', '', $timestamp);

        try {
            $dt = DateTime::createFromFormat('d-M-Y H:i:s', $timestamp);
            if ($dt) {
                return $dt->format('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
            // Ignore
        }

        return $timestamp;
    }

    /**
     * Parse syslog line.
     * Format: Mon DD HH:MM:SS hostname process[PID]: message.
     */
    private function parseSyslogLine(string $line): ?array
    {
        // Syslog format: Jan 15 10:30:45 hostname process[1234]: message
        $pattern = '/^(\w{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})\s+(\S+)\s+(\S+?)(?:\[\d+\])?:\s*(.+)$/';

        if (preg_match($pattern, $line, $matches)) {
            $message = $matches[4];
            $process = $matches[3];

            // Filter for relevant processes and error keywords
            $relevantProcesses = ['php', 'php-fpm', 'nginx', 'mysql', 'mariadb', 'redis', 'supervisor'];
            $errorKeywords = ['error', 'fail', 'fatal', 'critical', 'exception', 'denied', 'refused'];

            $isRelevant = false;
            foreach ($relevantProcesses as $proc) {
                if (stripos($process, $proc) !== false) {
                    $isRelevant = true;
                    break;
                }
            }

            if (! $isRelevant) {
                foreach ($errorKeywords as $keyword) {
                    if (stripos($message, $keyword) !== false) {
                        $isRelevant = true;
                        break;
                    }
                }
            }

            if ($isRelevant) {
                // Determine level based on keywords
                $level = 'INFO';
                if (preg_match('/\b(fatal|critical|emergency)\b/i', $message)) {
                    $level = 'CRITICAL';
                } elseif (preg_match('/\b(error|fail|exception)\b/i', $message)) {
                    $level = 'ERROR';
                } elseif (preg_match('/\b(warn|denied|refused)\b/i', $message)) {
                    $level = 'WARNING';
                }

                return [
                    'timestamp' => $this->parseSyslogTimestamp($matches[1]),
                    'level' => $level,
                    'environment' => $process,
                    'message' => $message,
                    'full_message' => $line,
                    'has_stack_trace' => false,
                ];
            }
        }

        return null;
    }

    /**
     * Parse syslog timestamp to standard format.
     */
    private function parseSyslogTimestamp(string $timestamp): string
    {
        // Format: Jan 15 10:30:45
        try {
            $year = date('Y');
            $dt = DateTime::createFromFormat('M j H:i:s', $timestamp);
            if ($dt) {
                $dt->setDate((int) $year, (int) $dt->format('m'), (int) $dt->format('d'));

                return $dt->format('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
            // Ignore
        }

        return $timestamp;
    }

    /**
     * Get list of available log files.
     */
    private function getAvailableLogFiles(): array
    {
        $logPath = storage_path('logs');
        $files = [];

        if (File::isDirectory($logPath)) {
            $logFiles = File::files($logPath);

            foreach ($logFiles as $file) {
                if ($file->getExtension() === 'log') {
                    $files[] = [
                        'name' => $file->getFilename(),
                        'size' => $file->getSize(),
                        'modified' => $file->getMTime(),
                    ];
                }
            }

            usort($files, fn ($a, $b) => $b['modified'] <=> $a['modified']);
        }

        return $files;
    }

    /**
     * Clear the log file.
     */
    public function clear(Request $request)
    {
        $this->authorize('admin-only');

        $source = $request->input('source', 'laravel');

        if ($source === 'laravel') {
            $logFile = storage_path('logs/laravel.log');

            if (File::exists($logFile)) {
                File::put($logFile, '');
            }

            return redirect()->route('admin.error-logs')->with('success', 'Laravel log file cleared successfully.');
        }

        return redirect()->route('admin.error-logs', ['source' => $source])
            ->with('error', 'System log files cannot be cleared from the admin panel.');
    }

    /**
     * Download the log file.
     */
    public function download(Request $request)
    {
        $this->authorize('admin-only');

        $source = $request->input('source', 'laravel');

        if ($source === 'laravel') {
            $logFile = storage_path('logs/laravel.log');
            $filename = 'laravel-' . date('Y-m-d-His') . '.log';
        } else {
            $logFile = $this->findSystemLog($source);
            $filename = $source . '-' . date('Y-m-d-His') . '.log';
        }

        if ($logFile === null || ! File::exists($logFile)) {
            return redirect()->route('admin.error-logs', ['source' => $source])
                ->with('error', 'Log file not found.');
        }

        if (! is_readable($logFile)) {
            return redirect()->route('admin.error-logs', ['source' => $source])
                ->with('error', 'Log file is not readable.');
        }

        return response()->download($logFile, $filename);
    }
}
