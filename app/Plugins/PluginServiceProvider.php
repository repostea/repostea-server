<?php

declare(strict_types=1);

namespace App\Plugins;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider that initializes the plugin system.
 */
final class PluginServiceProvider extends ServiceProvider
{
    /**
     * Register plugin services.
     */
    public function register(): void
    {
        // Register configuration
        $this->mergeConfigFrom($this->configPath(), 'plugins');

        // Register PluginManager as singleton
        $this->app->singleton(PluginManager::class, fn ($app) => new PluginManager($app));

        // Load enabled plugins
        $this->app->make(PluginManager::class)->loadPlugins();
    }

    /**
     * Bootstrap plugin services.
     */
    public function boot(): void
    {
        // Boot all plugins first (registers services, listeners)
        $pluginManager = $this->app->make(PluginManager::class);
        $pluginManager->bootPlugins();

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => config_path('plugins.php'),
            ], 'plugins-config');

            // Collect all plugin commands
            $pluginCommands = [];
            foreach ($pluginManager->all() as $plugin) {
                $pluginCommands = array_merge($pluginCommands, $plugin->getCommands());
            }

            // Register plugin management commands + plugin commands
            $this->commands(array_merge([
                Console\PluginListCommand::class,
                Console\PluginMakeCommand::class,
            ], $pluginCommands));
        }

        // Register plugin scheduled tasks
        $this->registerScheduledTasks();
    }

    /**
     * Register scheduled tasks from plugins.
     */
    private function registerScheduledTasks(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $pluginManager = $this->app->make(PluginManager::class);

            foreach ($pluginManager->getScheduledTasks() as $task) {
                $frequency = $task['frequency'];

                if (isset($task['job'])) {
                    $scheduled = $schedule->job(new $task['job'](...($task['args'] ?? [])));
                } elseif (isset($task['command'])) {
                    $scheduled = $schedule->command($task['command'], $task['args'] ?? []);
                } else {
                    continue;
                }

                // Apply frequency (supports cron expressions like "*/7 * * * *")
                if (str_contains($frequency, '*') || str_contains($frequency, ' ')) {
                    // It's a cron expression
                    $scheduled->cron($frequency);
                } else {
                    match ($frequency) {
                        'everyMinute' => $scheduled->everyMinute(),
                        'everyTwoMinutes' => $scheduled->everyTwoMinutes(),
                        'everyThreeMinutes' => $scheduled->cron('*/3 * * * *'),
                        'everyFiveMinutes' => $scheduled->everyFiveMinutes(),
                        'everySevenMinutes' => $scheduled->cron('*/7 * * * *'),
                        'everyTenMinutes' => $scheduled->everyTenMinutes(),
                        'everyFifteenMinutes' => $scheduled->everyFifteenMinutes(),
                        'everyThirtyMinutes' => $scheduled->everyThirtyMinutes(),
                        'hourly' => $scheduled->hourly(),
                        'daily' => $scheduled->daily(),
                        'weekly' => $scheduled->weekly(),
                        default => $scheduled->everyFiveMinutes(),
                    };
                }
            }
        });
    }

    /**
     * Get the config file path.
     */
    private function configPath(): string
    {
        return __DIR__ . '/../../config/plugins.php';
    }
}
