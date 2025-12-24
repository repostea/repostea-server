<?php

declare(strict_types=1);

namespace App\Services;

use const OPENSSL_ALGO_SHA256;
use const OPENSSL_ALGO_SHA512;
use const PREG_SET_ORDER;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for verifying HTTP Signatures on incoming ActivityPub requests.
 *
 * Implements draft-cavage-http-signatures verification as used by Mastodon
 * and other ActivityPub implementations.
 */
final class HttpSignatureService
{
    private const CACHE_TTL = 3600; // 1 hour

    private const ALLOWED_ALGORITHMS = [
        'rsa-sha256',
        'rsa-sha512',
        'hs2019', // Modern algorithm identifier
    ];

    public function __construct(
        private readonly UrlValidationService $urlValidator,
    ) {}

    /**
     * Verify the HTTP Signature on an incoming request.
     *
     * @return array{valid: bool, error?: string, keyId?: string}
     */
    public function verifyRequest(Request $request): array
    {
        $signatureHeader = $request->header('Signature');

        if ($signatureHeader === null || $signatureHeader === '') {
            return ['valid' => false, 'error' => 'Missing Signature header'];
        }

        // Parse the Signature header
        $parsed = $this->parseSignatureHeader($signatureHeader);
        if ($parsed === null) {
            return ['valid' => false, 'error' => 'Invalid Signature header format'];
        }

        $keyId = $parsed['keyId'] ?? null;
        $algorithm = $parsed['algorithm'] ?? 'rsa-sha256';
        $headers = $parsed['headers'] ?? '(request-target) host date';
        $signature = $parsed['signature'] ?? null;

        if ($keyId === null || $signature === null) {
            return ['valid' => false, 'error' => 'Missing keyId or signature'];
        }

        // Validate algorithm
        if (! in_array(strtolower($algorithm), self::ALLOWED_ALGORITHMS, true)) {
            return ['valid' => false, 'error' => "Unsupported algorithm: {$algorithm}"];
        }

        // Verify the Date header is not too old (5 minutes tolerance)
        $dateHeader = $request->header('Date');
        if ($dateHeader !== null) {
            $requestTime = strtotime($dateHeader);
            if ($requestTime === false || abs(time() - $requestTime) > 300) {
                Log::debug('HttpSignature: Date header too old or invalid', [
                    'date' => $dateHeader,
                    'diff' => $requestTime !== false ? time() - $requestTime : 'parse_failed',
                ]);
                // Don't reject, just log - some implementations have clock skew
            }
        }

        // Verify Digest header if present
        $digestHeader = $request->header('Digest');
        if ($digestHeader !== null) {
            $body = $request->getContent();
            if (! $this->verifyDigest($digestHeader, $body)) {
                return ['valid' => false, 'error' => 'Digest mismatch'];
            }
        }

        // Fetch the public key
        $publicKey = $this->fetchPublicKey($keyId);
        if ($publicKey === null) {
            return ['valid' => false, 'error' => 'Could not fetch public key', 'keyId' => $keyId];
        }

        // Build the signature string
        $signatureString = $this->buildSignatureString($request, $headers);
        if ($signatureString === null) {
            return ['valid' => false, 'error' => 'Could not build signature string'];
        }

        // Verify the signature
        $signatureDecoded = base64_decode($signature, true);
        if ($signatureDecoded === false) {
            return ['valid' => false, 'error' => 'Invalid signature encoding'];
        }

        $result = openssl_verify(
            $signatureString,
            $signatureDecoded,
            $publicKey,
            $this->getOpenSslAlgorithm($algorithm),
        );

        if ($result === 1) {
            Log::debug('HttpSignature: Verification successful', ['keyId' => $keyId]);

            return ['valid' => true, 'keyId' => $keyId];
        }

        if ($result === 0) {
            Log::warning('HttpSignature: Signature verification failed', [
                'keyId' => $keyId,
                'headers' => $headers,
            ]);

            return ['valid' => false, 'error' => 'Signature verification failed', 'keyId' => $keyId];
        }

        Log::error('HttpSignature: OpenSSL error', [
            'error' => openssl_error_string(),
            'keyId' => $keyId,
        ]);

        return ['valid' => false, 'error' => 'OpenSSL error during verification'];
    }

    /**
     * Parse the Signature header into its components.
     *
     * @return array<string, string>|null
     */
    private function parseSignatureHeader(string $header): ?array
    {
        $result = [];

        // Match key="value" or key=value patterns
        $pattern = '/(\w+)=(?:"([^"]+)"|([^\s,]+))/';
        if (preg_match_all($pattern, $header, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2] !== '' ? $match[2] : ($match[3] ?? '');
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Verify the Digest header matches the body.
     */
    private function verifyDigest(string $digestHeader, string $body): bool
    {
        // Parse digest header (format: algorithm=base64value)
        if (preg_match('/^([A-Za-z0-9-]+)=(.+)$/', $digestHeader, $matches)) {
            $algorithm = strtoupper($matches[1]);
            $expectedDigest = $matches[2];

            $actualDigest = match ($algorithm) {
                'SHA-256' => base64_encode(hash('sha256', $body, true)),
                'SHA-512' => base64_encode(hash('sha512', $body, true)),
                default => null,
            };

            if ($actualDigest === null) {
                Log::warning('HttpSignature: Unsupported digest algorithm', ['algorithm' => $algorithm]);

                return false;
            }

            if ($actualDigest !== $expectedDigest) {
                Log::warning('HttpSignature: Digest mismatch', [
                    'expected' => $expectedDigest,
                    'actual' => $actualDigest,
                ]);

                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Fetch the public key from a keyId URL.
     */
    private function fetchPublicKey(string $keyId): ?string
    {
        // Extract the actor URL (keyId is usually actor#main-key)
        $actorUrl = preg_replace('/#.*$/', '', $keyId);

        // Validate URL for SSRF protection
        try {
            $this->urlValidator->validate($actorUrl);
        } catch (Exception $e) {
            Log::warning('HttpSignature: Invalid keyId URL', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        // Check cache first
        $cacheKey = 'activitypub:pubkey:' . md5($keyId);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
            ])
                ->timeout(10)
                ->get($actorUrl);

            if (! $response->successful()) {
                Log::warning('HttpSignature: Failed to fetch actor', [
                    'url' => $actorUrl,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $actorData = $response->json();
            if (! is_array($actorData)) {
                return null;
            }

            // Find the public key
            $publicKey = $this->extractPublicKey($actorData, $keyId);
            if ($publicKey === null) {
                Log::warning('HttpSignature: Public key not found in actor', [
                    'keyId' => $keyId,
                    'actorUrl' => $actorUrl,
                ]);

                return null;
            }

            // Cache the key
            Cache::put($cacheKey, $publicKey, self::CACHE_TTL);

            return $publicKey;
        } catch (Exception $e) {
            Log::error('HttpSignature: Error fetching public key', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract the public key from actor data.
     *
     * @param  array<string, mixed>  $actorData
     */
    private function extractPublicKey(array $actorData, string $keyId): ?string
    {
        // Check publicKey field (standard location)
        $publicKey = $actorData['publicKey'] ?? null;

        if (is_array($publicKey)) {
            // Single key object
            if (($publicKey['id'] ?? null) === $keyId || str_starts_with($keyId, (string) ($publicKey['id'] ?? ''))) {
                return $publicKey['publicKeyPem'] ?? null;
            }
            // If keyId doesn't match exactly, still use the key (some implementations vary)
            if (isset($publicKey['publicKeyPem'])) {
                return $publicKey['publicKeyPem'];
            }
        }

        // Some implementations use an array of keys
        if (isset($actorData['publicKeys']) && is_array($actorData['publicKeys'])) {
            foreach ($actorData['publicKeys'] as $key) {
                if (is_array($key) && ($key['id'] ?? null) === $keyId) {
                    return $key['publicKeyPem'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Build the signature string from request headers.
     */
    private function buildSignatureString(Request $request, string $headers): ?string
    {
        $headerNames = explode(' ', $headers);
        $parts = [];

        foreach ($headerNames as $headerName) {
            $headerName = strtolower(trim($headerName));

            if ($headerName === '(request-target)') {
                $method = strtolower($request->method());
                $path = $request->getRequestUri();
                $parts[] = "(request-target): {$method} {$path}";
            } elseif ($headerName === '(created)') {
                // Some implementations use (created) pseudo-header
                $parts[] = '(created): ' . time();
            } elseif ($headerName === '(expires)') {
                // Skip expires for now
                continue;
            } else {
                $value = $request->header($headerName);
                if ($value === null) {
                    Log::warning('HttpSignature: Missing header for signature', ['header' => $headerName]);

                    return null;
                }
                $parts[] = "{$headerName}: {$value}";
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Get the OpenSSL algorithm constant.
     */
    private function getOpenSslAlgorithm(string $algorithm): int
    {
        return match (strtolower($algorithm)) {
            'rsa-sha512' => OPENSSL_ALGO_SHA512,
            'hs2019', 'rsa-sha256' => OPENSSL_ALGO_SHA256,
            default => OPENSSL_ALGO_SHA256,
        };
    }

    /**
     * Check if signature verification should be enforced.
     *
     * Returns false in development/testing or when explicitly disabled.
     */
    public function shouldEnforce(): bool
    {
        // Check config flag
        if (config('activitypub.signatures.require') === false) {
            return false;
        }

        // Don't enforce in testing
        if (app()->environment('testing')) {
            return false;
        }

        return true;
    }

    /**
     * Check if failed verifications should be logged.
     */
    public function shouldLogFailures(): bool
    {
        return (bool) config('activitypub.signatures.log_failures', true);
    }
}
