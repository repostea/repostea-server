<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Fediverse Login Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Mastodon/Mbin/Fediverse login integration.
    | This controls OAuth login from federated instances, NOT post federation.
    | For post federation, see config/activitypub.php
    |
    */

    // Enable or disable fediverse login (Mastodon, Mbin, etc.)
    'enabled' => env('FEDIVERSE_LOGIN_ENABLED', false),

    // List of blocked instances (users cannot login from these)
    'blocked_instances' => array_filter(explode(',', env('FEDIVERSE_LOGIN_BLOCKED_INSTANCES', '')), static fn ($value) => $value !== ''),

    // Minimum account age (in days) on the remote instance to allow login
    // Set to 0 to allow any account
    'min_account_age_days' => env('FEDIVERSE_LOGIN_MIN_ACCOUNT_AGE', 0),

    // Auto-approve federated users or require manual approval
    'auto_approve' => env('FEDIVERSE_LOGIN_AUTO_APPROVE', true),
];
