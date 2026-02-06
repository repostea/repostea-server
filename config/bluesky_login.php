<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Bluesky Login Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Bluesky/AT Protocol OAuth login integration.
    | Uses the revolution/laravel-bluesky package with Socialite driver.
    |
    | Generate a private key with: php artisan bluesky:new-private-key
    |
    */

    // Enable or disable Bluesky login
    'enabled' => env('BLUESKY_LOGIN_ENABLED', false),

    // Private key for DPoP/PKCE (generated with artisan command)
    'private_key' => env('BLUESKY_OAUTH_PRIVATE_KEY', ''),

    // Minimum account age (in days) on Bluesky to allow login
    // Set to 0 to allow any account
    'min_account_age_days' => env('BLUESKY_MIN_ACCOUNT_AGE', 0),

    // Auto-approve Bluesky users or require manual approval
    'auto_approve' => env('BLUESKY_AUTO_APPROVE', true),
];
