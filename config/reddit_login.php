<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Reddit Login Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Reddit OAuth login integration.
    | Register your app at: https://www.reddit.com/prefs/apps
    |
    */

    // Enable or disable Reddit login
    'enabled' => env('REDDIT_LOGIN_ENABLED', false),

    // Reddit OAuth client credentials
    'client_id' => env('REDDIT_CLIENT_ID', ''),
    'client_secret' => env('REDDIT_CLIENT_SECRET', ''),

    // Reddit username for User-Agent (required by Reddit API)
    'bot_username' => env('REDDIT_BOT_USERNAME', 'repostea'),

    // Minimum account age (in days) on Reddit to allow login
    // Set to 0 to allow any account
    'min_account_age_days' => env('REDDIT_MIN_ACCOUNT_AGE', 0),

    // Auto-approve Reddit users or require manual approval
    'auto_approve' => env('REDDIT_AUTO_APPROVE', true),
];
