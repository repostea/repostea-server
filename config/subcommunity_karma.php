<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Subcommunity Creator Karma Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines how much karma subcommunity creators earn
    | for different types of activity within their communities.
    |
    */

    // Karma for member growth
    'member_join' => 1,              // 1 karma per new member
    'milestone_10_members' => 10,    // Small bonus at 10 members
    'milestone_100_members' => 50,   // Medium bonus at 100 members
    'milestone_1000_members' => 200, // Large bonus at 1000 members

    // Karma for content (2-3% of author's karma)
    'post_created' => 0.3,        // 3% of 10 (base post karma)
    'post_frontpage' => 2,        // Small bonus for reaching frontpage
    'comment_created' => 0.15,    // 3% of 5 (base comment karma)
    'valuable_insight' => 1,      // Bonus for content marked as valuable

    // Karma for engagement (1% of vote karma)
    'post_upvote' => 0.1,         // 1% of 10
    'comment_upvote' => 0.05,     // 1% of 5

    // Karma for moderation
    'report_resolved' => 1,       // 1 karma per resolved report
    'clean_month_bonus' => 25,    // Bonus for 30 days without reports

    // No size multipliers (keep simple and predictable)
    'size_multipliers' => [
        'small' => 1.0,      // < 100 members
        'medium' => 1.0,     // 100-1000 members
        'large' => 1.0,      // 1000-10000 members
        'massive' => 1.0,    // > 10000 members
    ],

    // Restrictive limits
    'max_karma_per_day_per_sub' => 50,  // Max 50 karma per day per sub
    'max_karma_per_event' => 5,         // Max 5 karma per individual event
];
