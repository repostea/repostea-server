<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Action-Based Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file defines rate limits for various actions in your application.
    | Each action can have different limits, and you can enable karma-based multipliers
    | to give more lenient limits to users with higher reputation.
    |
    | Configuration options per action:
    | - max_attempts: Maximum number of attempts allowed
    | - decay_minutes: Time window in minutes for the rate limit
    | - name: Human-readable name for error messages
    | - use_karma_multiplier: Whether to apply karma-based limit increases
    | - auto_ban_threshold: Number of violations before auto-ban (0 = disabled)
    | - auto_ban_duration_hours: Duration of automatic ban in hours
    |
    */

    'actions' => [
        // Post creation limits
        'create_post' => [
            'max_attempts' => 10,             // 10 posts per hour for new users
            'decay_minutes' => 60,            // 1 hour window
            'name' => 'post creation',
            'use_karma_multiplier' => true,   // Higher karma = more posts allowed
            'auto_ban_threshold' => 10,       // Ban after 10 violations in 24h
            'auto_ban_duration_hours' => 24,  // 24-hour ban
        ],

        // Post update limits
        'update_post' => [
            'max_attempts' => 30,             // 30 post edits per hour
            'decay_minutes' => 60,
            'name' => 'post updates',
            'use_karma_multiplier' => false,  // No karma multiplier needed for edits
            'auto_ban_threshold' => 0,        // No auto-ban for editing
            'auto_ban_duration_hours' => 0,
        ],

        // Comment creation limits
        'create_comment' => [
            'max_attempts' => 20,             // 20 comments per hour
            'decay_minutes' => 60,
            'name' => 'comment creation',
            'use_karma_multiplier' => true,
            'auto_ban_threshold' => 15,
            'auto_ban_duration_hours' => 12,
        ],

        // Comment update limits
        'update_comment' => [
            'max_attempts' => 30,             // 30 comment edits per hour
            'decay_minutes' => 60,
            'name' => 'comment updates',
            'use_karma_multiplier' => false,
            'auto_ban_threshold' => 0,
            'auto_ban_duration_hours' => 0,
        ],

        // Vote limits (upvote/downvote)
        'vote' => [
            'max_attempts' => 100,            // 100 votes per hour
            'decay_minutes' => 60,
            'name' => 'voting',
            'use_karma_multiplier' => true,
            'auto_ban_threshold' => 0,        // No auto-ban for voting (too strict)
            'auto_ban_duration_hours' => 0,
        ],

        // Magazine/Community creation limits
        'create_sub' => [
            'max_attempts' => 3,              // 3 communities per day
            'decay_minutes' => 1440,          // 24 hours
            'name' => 'community creation',
            'use_karma_multiplier' => true,
            'auto_ban_threshold' => 5,
            'auto_ban_duration_hours' => 72,  // 3 days
        ],

        // Report submission limits
        'create_report' => [
            'max_attempts' => 10,             // 10 reports per hour
            'decay_minutes' => 60,
            'name' => 'report submission',
            'use_karma_multiplier' => false,  // No karma advantage for reports
            'auto_ban_threshold' => 20,       // Strict for report spam
            'auto_ban_duration_hours' => 48,
        ],

        // Message/DM limits
        'send_message' => [
            'max_attempts' => 30,             // 30 messages per hour
            'decay_minutes' => 60,
            'name' => 'sending messages',
            'use_karma_multiplier' => true,
            'auto_ban_threshold' => 10,
            'auto_ban_duration_hours' => 24,
        ],

        // Profile update limits
        'update_profile' => [
            'max_attempts' => 10,             // 10 profile updates per hour
            'decay_minutes' => 60,
            'name' => 'profile updates',
            'use_karma_multiplier' => false,
            'auto_ban_threshold' => 0,
            'auto_ban_duration_hours' => 0,
        ],

        // Media upload limits
        'upload_media' => [
            'max_attempts' => 20,             // 20 uploads per hour
            'decay_minutes' => 60,
            'name' => 'media uploads',
            'use_karma_multiplier' => true,
            'auto_ban_threshold' => 15,
            'auto_ban_duration_hours' => 24,
        ],

        // Invitation limits
        'send_invitation' => [
            'max_attempts' => 5,              // 5 invitations per day
            'decay_minutes' => 1440,          // 24 hours
            'name' => 'sending invitations',
            'use_karma_multiplier' => true,
            'auto_ban_threshold' => 0,
            'auto_ban_duration_hours' => 0,
        ],

        // AI content generation limits
        'generate_ai_content' => [
            'max_attempts' => 10,             // 10 AI generations per hour
            'decay_minutes' => 60,
            'name' => 'AI content generation',
            'use_karma_multiplier' => true,
            'auto_ban_threshold' => 20,
            'auto_ban_duration_hours' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Karma-Based Multipliers
    |--------------------------------------------------------------------------
    |
    | Define how karma affects rate limits. Higher karma = more lenient limits.
    | Format: karma_threshold => multiplier
    |
    | Example: A user with 500 karma creating posts:
    | - Base limit: 5 posts/hour
    | - With 2.0x multiplier: 10 posts/hour
    |
    */

    'karma_multipliers' => [
        0 => 1.0,      // 0-99 karma: Normal limits (1x)
        100 => 1.5,    // 100-499 karma: 50% more (1.5x)
        500 => 2.0,    // 500-999 karma: Double (2x)
        1000 => 2.5,   // 1000-4999 karma: 2.5x
        5000 => 3.0,   // 5000-9999 karma: Triple (3x)
        10000 => 4.0,  // 10000+ karma: 4x
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Alerts
    |--------------------------------------------------------------------------
    |
    | Configure thresholds for admin alerts and monitoring
    |
    */

    'monitoring' => [
        // Alert admins when a user exceeds X violations per hour
        'alert_threshold_per_hour' => 50,

        // Alert admins when overall rate limit violations exceed X per hour
        'global_alert_threshold_per_hour' => 500,

        // Days to retain rate limit logs in database
        'log_retention_days' => 30,

        // Enable real-time monitoring dashboard
        'enable_dashboard' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Suspicious Pattern Detection
    |--------------------------------------------------------------------------
    |
    | Patterns that indicate abuse or bot behavior
    |
    */

    'suspicious_patterns' => [
        // Flag users who hit rate limits on multiple actions within X minutes
        'multiple_action_violations_window_minutes' => 10,
        'multiple_action_violations_threshold' => 3,

        // Flag rapid-fire requests (X requests in Y seconds)
        'rapid_fire_requests' => 10,
        'rapid_fire_window_seconds' => 10,

        // Flag users creating very similar content
        'duplicate_content_similarity_threshold' => 0.9, // 90% similar
        'duplicate_content_check_window_hours' => 24,
    ],
];
