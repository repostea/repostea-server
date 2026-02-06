<?php

declare(strict_types=1);

$baseConfig = [
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
    | For instance-specific configurations, create a plugins.renegados.php file
    | (or similar) that will be automatically merged with this base config.
    |
    */
];

// Load instance-specific plugin config if it exists
$instanceConfig = __DIR__ . '/plugins.renegados.php';
if (file_exists($instanceConfig)) {
    $baseConfig = array_merge($baseConfig, require $instanceConfig);
}

return $baseConfig;
