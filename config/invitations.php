<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Invitation System Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how invitation codes work in your application, including
    | limits, expiration defaults, and karma-based bonuses.
    |
    */

    // Default limit of invitations a user can create
    'default_limit' => env('INVITATION_DEFAULT_LIMIT', 5),

    // Default expiration duration for invitations (in days)
    'default_expiration_days' => env('INVITATION_EXPIRATION_DAYS', 30),

    // Default maximum uses per invitation
    'default_max_uses' => env('INVITATION_DEFAULT_MAX_USES', 1),

    /*
    |--------------------------------------------------------------------------
    | Karma-Based Invitation Limits
    |--------------------------------------------------------------------------
    |
    | Users with more karma get more available invitations.
    | The system finds the highest threshold the user has reached.
    |
    | Format: karma_threshold => invitation_limit
    |
    */

    'karma_limits' => [
        0 => 5,        // 0-99 karma: 5 invitations (default)
        100 => 10,     // 100-499 karma: 10 invitations
        500 => 20,     // 500-999 karma: 20 invitations
        1000 => 30,    // 1000-4999 karma: 30 invitations
        5000 => 50,    // 5000-9999 karma: 50 invitations
        10000 => 100,  // 10000+ karma: 100 invitations
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest User Restrictions
    |--------------------------------------------------------------------------
    |
    | Can guest users create invitations?
    |
    */

    'allow_guest_invitations' => false,

    /*
    |--------------------------------------------------------------------------
    | Verified Email Requirement
    |--------------------------------------------------------------------------
    |
    | Require verified email to create invitations?
    |
    */

    'require_verified_email' => true,

    /*
    |--------------------------------------------------------------------------
    | Minimum Account Age
    |--------------------------------------------------------------------------
    |
    | Minimum account age (in days) to create invitations.
    | This prevents new accounts from spamming invitations.
    |
    */

    'minimum_account_age_days' => env('INVITATION_MIN_ACCOUNT_AGE', 0),

    /*
    |--------------------------------------------------------------------------
    | Minimum Karma Required
    |--------------------------------------------------------------------------
    |
    | Minimum karma required to create invitations.
    |
    */

    'minimum_karma' => env('INVITATION_MIN_KARMA', 0),

    /*
    |--------------------------------------------------------------------------
    | Admin Override
    |--------------------------------------------------------------------------
    |
    | Special limits for admins and moderators.
    |
    */

    'admin_unlimited' => true, // Admins have unlimited invitations

    'moderator_limit' => 50,   // Moderators have special limit
];
