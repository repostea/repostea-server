<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Login Enabled
    |--------------------------------------------------------------------------
    |
    | This controls whether users can log in using Telegram Login Widget.
    |
    */
    'login_enabled' => env('TELEGRAM_LOGIN_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Bot Token
    |--------------------------------------------------------------------------
    |
    | The bot token from @BotFather. Required for verifying login data.
    | Get it from https://t.me/BotFather
    |
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Bot Username
    |--------------------------------------------------------------------------
    |
    | The bot username (without @). Required for the login widget.
    |
    */
    'bot_username' => env('TELEGRAM_BOT_USERNAME'),
];
