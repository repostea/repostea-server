<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Twitter API Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are used to authenticate with the Twitter/X API.
    | You can obtain them from https://developer.twitter.com
    |
    */

    'api_key' => env('TWITTER_API_KEY'),
    'api_secret' => env('TWITTER_API_SECRET'),
    'access_token' => env('TWITTER_ACCESS_TOKEN'),
    'access_token_secret' => env('TWITTER_ACCESS_TOKEN_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Auto-posting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic posting behavior.
    |
    */

    'auto_post_enabled' => env('TWITTER_AUTO_POST_ENABLED', false),

    // Minimum votes required for a post to be automatically tweeted
    'min_votes_to_post' => env('TWITTER_MIN_VOTES_TO_POST', 50),

    // Automatically post original articles (content_type = 'text')
    'auto_post_original_articles' => env('TWITTER_AUTO_POST_ORIGINAL_ARTICLES', true),

    // Delay in minutes before posting (to allow editing after frontpage)
    'post_delay_minutes' => env('TWITTER_POST_DELAY_MINUTES', 30),

    // Hashtags to append to tweets (comma-separated in .env)
    'default_hashtags' => array_filter(
        array_map('trim', explode(',', env('TWITTER_DEFAULT_HASHTAGS', 'Repostea'))),
    ),

];
