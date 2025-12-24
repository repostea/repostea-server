<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | ActivityPub Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for ActivityPub federation (outbound publishing).
    |
    */

    // Enable or disable ActivityPub publishing
    'enabled' => env('ACTIVITYPUB_ENABLED', false),

    // The domain for ActivityPub identities (where endpoints live)
    'domain' => env('ACTIVITYPUB_DOMAIN', env('APP_URL', 'https://api.example.com')),

    // Public domain for actor handle (e.g., @repostea@example.com)
    // If different from domain, you need to set up WebFinger redirect on the public domain
    'public_domain' => env('ACTIVITYPUB_PUBLIC_DOMAIN', env('ACTIVITYPUB_DOMAIN', env('APP_URL'))),

    // Actor configuration
    'actor' => [
        // Username for the main actor (e.g., @repostea@domain.com)
        'username' => env('ACTIVITYPUB_USERNAME', 'repostea'),

        // Display name shown in Mastodon/etc
        'name' => env('ACTIVITYPUB_NAME', 'Repostea'),

        // Bio/summary for the actor
        'summary' => env('ACTIVITYPUB_SUMMARY', 'Community content aggregation platform'),

        // Avatar URL (optional)
        'icon' => env('ACTIVITYPUB_ICON', null),
    ],

    // HTTP client settings
    'http' => [
        'timeout' => env('ACTIVITYPUB_HTTP_TIMEOUT', 10),
        'retries' => env('ACTIVITYPUB_HTTP_RETRIES', 3),
        'retry_delay' => env('ACTIVITYPUB_HTTP_RETRY_DELAY', 5),
    ],

    // Auto-accept follow requests
    'auto_accept_follows' => env('ACTIVITYPUB_AUTO_ACCEPT_FOLLOWS', true),

    // Queue name for ActivityPub jobs
    'queue' => env('ACTIVITYPUB_QUEUE', 'default'),

    // HTTP Signature verification settings
    'signatures' => [
        // Require valid HTTP Signatures on incoming requests
        // Set to false to accept unsigned requests (not recommended for production)
        'require' => env('ACTIVITYPUB_REQUIRE_SIGNATURES', true),

        // Log failed signature verifications even when not enforcing
        'log_failures' => env('ACTIVITYPUB_LOG_SIGNATURE_FAILURES', true),
    ],

    // SSRF (Server-Side Request Forgery) protection settings
    'ssrf' => [
        // Validate that remote URLs don't resolve to private IP ranges
        // Disable only in testing environments where DNS may not be available
        'validate_dns' => env('ACTIVITYPUB_VALIDATE_DNS', true),
    ],
];
