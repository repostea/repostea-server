<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled Plugins
    |--------------------------------------------------------------------------
    |
    | List of enabled plugin IDs. Can be set via ENABLED_PLUGINS environment
    | variable as a comma-separated list, or as an array here.
    |
    | Example .env: ENABLED_PLUGINS=rss-import,twitter-bot
    |
    */
    'enabled' => env('ENABLED_PLUGINS', ''),

    /*
    |--------------------------------------------------------------------------
    | Plugin-specific Configuration
    |--------------------------------------------------------------------------
    |
    | Add configuration for individual plugins below.
    | Access in plugins via: $this->config('key') or config('plugins.plugin-id.key')
    |
    */
];
