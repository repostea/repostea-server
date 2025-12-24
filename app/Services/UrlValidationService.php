<?php

declare(strict_types=1);

namespace App\Services;

use const FILTER_VALIDATE_IP;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * URL validation service to prevent SSRF attacks.
 *
 * Restrictions:
 * - Only HTTPS scheme
 * - Only port 443
 * - Only domain names (no direct IPs)
 * - Validates resolved IPs against private/reserved ranges
 */
final class UrlValidationService
{
    /**
     * @var array<string> CIDR ranges that are blocked
     */
    private const BLOCKED_CIDR_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/29',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    /**
     * @var array<string> Cloud metadata endpoints
     */
    private const BLOCKED_HOSTS = [
        'metadata.google.internal',
        'metadata.goog',
    ];

    /**
     * Validate that a URL is safe for external requests.
     *
     * @throws InvalidArgumentException
     */
    public function validate(string $url): void
    {
        $this->validateNotEmpty($url);
        $parts = $this->parseUrl($url);
        $this->validateNoCredentials($parts);
        $this->validateScheme($parts);
        $this->validatePort($parts);
        $this->validateHost($parts);
    }

    /**
     * Validate a federation instance hostname (for OAuth services).
     * Validates the hostname without requiring a full URL.
     *
     * @throws InvalidArgumentException
     */
    public function validateInstance(string $instance): void
    {
        $instance = strtolower(trim($instance));

        if ($instance === '') {
            throw new InvalidArgumentException('Instance cannot be empty');
        }

        // Remove any protocol prefix if accidentally included
        $instance = preg_replace('#^https?://#', '', $instance);
        $instance = rtrim($instance, '/');

        $this->validateNotIpAddress($instance);
        $this->validateHasDot($instance);
        $this->validateNotBlockedHost($instance);
        $this->validateResolvedIps($instance);
    }

    private function validateNotEmpty(string $url): void
    {
        if (trim($url) === '') {
            throw new InvalidArgumentException('URL cannot be empty');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw new InvalidArgumentException('Invalid URL format');
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function validateNoCredentials(array $parts): void
    {
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Credentials in URL not allowed');
        }
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function validateScheme(array $parts): void
    {
        $scheme = strtolower($parts['scheme'] ?? '');

        if ($scheme !== 'https') {
            throw new InvalidArgumentException('Only HTTPS URLs allowed');
        }
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function validatePort(array $parts): void
    {
        if (isset($parts['port']) && $parts['port'] !== 443) {
            throw new InvalidArgumentException('Only port 443 allowed');
        }
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function validateHost(array $parts): void
    {
        $host = strtolower($parts['host']);

        $this->validateNotIpAddress($host);
        $this->validateHasDot($host);
        $this->validateNotBlockedHost($host);
        $this->validateResolvedIps($host);
    }

    private function validateNotIpAddress(string $host): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Direct IP addresses not allowed');
        }
    }

    private function validateHasDot(string $host): void
    {
        if (! str_contains($host, '.')) {
            throw new InvalidArgumentException('Invalid domain name');
        }
    }

    private function validateNotBlockedHost(string $host): void
    {
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new InvalidArgumentException('Host not allowed');
        }
    }

    private function validateResolvedIps(string $host): void
    {
        // Skip DNS validation if disabled (e.g., in testing environments)
        if (! config('activitypub.ssrf.validate_dns', true)) {
            return;
        }

        // Cache DNS lookups for 1 hour to reduce overhead
        $cacheKey = "ssrf:dns:{$host}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            // Cached result: 'blocked' means previously blocked, array of IPs means allowed
            if ($cached === 'blocked') {
                throw new InvalidArgumentException('Domain resolves to blocked IP range');
            }
            if ($cached === 'unresolved') {
                throw new InvalidArgumentException('Could not resolve domain');
            }

            // Valid cached IPs, skip DNS lookup
            return;
        }

        $ips = gethostbynamel($host);

        if ($ips === false || $ips === []) {
            Cache::put($cacheKey, 'unresolved', now()->addHours(1));
            throw new InvalidArgumentException('Could not resolve domain');
        }

        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                Cache::put($cacheKey, 'blocked', now()->addHours(1));
                throw new InvalidArgumentException('Domain resolves to blocked IP range');
            }
        }

        // Cache valid IPs
        Cache::put($cacheKey, $ips, now()->addHours(1));
    }

    private function isBlockedIp(string $ip): bool
    {
        foreach (self::BLOCKED_CIDR_RANGES as $cidr) {
            if ($this->ipMatchesCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskBits = ~((1 << (32 - (int) $mask)) - 1);

        return ($ipLong & $maskBits) === ($subnetLong & $maskBits);
    }
}
