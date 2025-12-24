<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Frontpage Settings
    |--------------------------------------------------------------------------
    */

    // Minimum votes required to be eligible for frontpage
    'frontpage_min_votes' => env('FRONTPAGE_MIN_VOTES', 2),

    // Maximum posts allowed on frontpage (in last 24 hours)
    'max_frontpage_posts' => env('MAX_FRONTPAGE_POSTS', 24),

    // Maximum age (in hours) for a post to be eligible for frontpage
    // After this time, posts cannot enter frontpage even with enough votes
    'frontpage_max_age_hours' => env('FRONTPAGE_MAX_AGE_HOURS', 48),

    // Chance (%) to promote a post each cron execution (adds randomness to timing)
    // 50 = ~10 min avg interval, 33 = ~15 min avg, 25 = ~20 min avg
    'frontpage_promotion_chance' => env('FRONTPAGE_PROMOTION_CHANCE', 50),

    /*
    |--------------------------------------------------------------------------
    | Time-based Restrictions
    |--------------------------------------------------------------------------
    */

    // Maximum age (in days) for voting on posts
    // After this time, users cannot vote on the post
    'voting_max_age_days' => env('VOTING_MAX_AGE_DAYS', 7),

    // Maximum age (in days) for commenting on posts
    // After this time, users cannot add new comments
    // Set to 0 to allow comments forever
    'commenting_max_age_days' => env('COMMENTING_MAX_AGE_DAYS', 0),
];
