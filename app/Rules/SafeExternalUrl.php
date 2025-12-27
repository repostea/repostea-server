<?php

declare(strict_types=1);

namespace App\Rules;

use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use const PHP_URL_HOST;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a URL does not point to private/internal IP addresses.
 * Prevents SSRF (Server-Side Request Forgery) attacks.
 */
final class SafeExternalUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('validation.url'));

            return;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! $host) {
            $fail(__('validation.url'));

            return;
        }

        // Block localhost variations
        $blockedHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
        if (in_array(strtolower($host), $blockedHosts, true)) {
            $fail(__('validation.safe_external_url'));

            return;
        }

        // Resolve hostname to IP
        $ip = gethostbyname($host);

        // If gethostbyname returns the same string, it couldn't resolve
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            // Could not resolve - might be internal DNS, allow but log
            return;
        }

        // Check if it's a private or reserved IP
        if ($this->isPrivateOrReservedIp($ip)) {
            $fail(__('validation.safe_external_url'));
        }
    }

    /**
     * Check if an IP address is private or reserved.
     */
    private function isPrivateOrReservedIp(string $ip): bool
    {
        // filter_var with FILTER_FLAG_NO_PRIV_RANGE and FILTER_FLAG_NO_RES_RANGE
        // returns false if the IP is private or reserved
        $result = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        return $result === false;
    }
}
