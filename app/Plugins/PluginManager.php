<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Plugins\Contracts\PluginInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Manages plugin discovery, loading, and lifecycle.
 */
final class PluginManager
{
    /**
     * Loaded plugin instances.
     *
     * @var array<string, PluginInterface>
     */
    private array $plugins = [];

    /**
     * Plugin metadata cache.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $metadata = [];

    /**
     * Whether plugins have been booted.
     */
    private bool $booted = false;

    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * Get the plugins directory path.
     */
    public function getPluginsPath(): string
    {
        return base_path('plugins');
    }

    /**
     * Get list of enabled plugin IDs from configuration.
     *
     * @return array<int, string>
     */
    public function getEnabledPluginIds(): array
    {
        $enabled = config('plugins.enabled', '');

        if (is_string($enabled)) {
            return array_filter(array_map('trim', explode(',', $enabled)));
        }

        return is_array($enabled) ? $enabled : [];
    }

    /**
     * Check if a plugin is enabled.
     */
    public function isEnabled(string $pluginId): bool
    {
        return in_array($pluginId, $this->getEnabledPluginIds(), true);
    }

    /**
     * Discover all available plugins.
     *
     * @return array<string, array<string, mixed>>
     */
    public function discover(): array
    {
        $pluginsPath = $this->getPluginsPath();

        if (! File::isDirectory($pluginsPath)) {
            return [];
        }

        $discovered = [];
        $directories = File::directories($pluginsPath);

        foreach ($directories as $directory) {
            $pluginId = basename($directory);
            $metadataFile = $directory . '/plugin.json';

            if (! File::exists($metadataFile)) {
                continue;
            }

            $content = File::get($metadataFile);
            $metadata = json_decode($content, true);

            if (! is_array($metadata)) {
                Log::warning("Invalid plugin.json in plugin: {$pluginId}");

                continue;
            }

            $metadata['id'] = $pluginId;
            $metadata['path'] = $directory;
            $metadata['enabled'] = $this->isEnabled($pluginId);

            $discovered[$pluginId] = $metadata;
        }

        $this->metadata = $discovered;

        return $discovered;
    }

    /**
     * Load and register all enabled plugins.
     */
    public function loadPlugins(): void
    {
        $this->discover();

        foreach ($this->metadata as $pluginId => $metadata) {
            if (! $metadata['enabled']) {
                continue;
            }

            try {
                $this->loadPlugin($pluginId, $metadata);
            } catch (Throwable $e) {
                Log::error("Failed to load plugin: {$pluginId}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Load a single plugin.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function loadPlugin(string $pluginId, array $metadata): void
    {
        $mainClass = $metadata['main'] ?? null;
        $path = $metadata['path'] ?? null;

        if (! $mainClass || ! $path) {
            throw new RuntimeException("Plugin {$pluginId} missing 'main' class in plugin.json");
        }

        // Register plugin autoloader
        $this->registerPluginAutoloader($pluginId, $path, $metadata['namespace'] ?? null);

        // Instantiate plugin
        if (! class_exists($mainClass)) {
            throw new RuntimeException("Plugin main class not found: {$mainClass}");
        }

        $plugin = $this->app->make($mainClass);

        if (! $plugin instanceof PluginInterface) {
            throw new RuntimeException("Plugin {$pluginId} must implement PluginInterface");
        }

        // Check dependencies
        if (! $plugin->checkDependencies()) {
            $deps = implode(', ', $plugin->getDependencies());
            throw new RuntimeException("Plugin {$pluginId} has unmet dependencies: {$deps}");
        }

        // Register plugin
        $plugin->register();
        $this->plugins[$pluginId] = $plugin;

        Log::info("Plugin loaded: {$pluginId} v{$plugin->getVersion()}");
    }

    /**
     * Register autoloader for plugin classes.
     */
    private function registerPluginAutoloader(string $pluginId, string $path, ?string $namespace = null): void
    {
        $namespace = $namespace ?? 'Plugins\\' . str_replace('-', '', ucwords($pluginId, '-')) . '\\';

        spl_autoload_register(function (string $class) use ($namespace, $path): void {
            if (! str_starts_with($class, $namespace)) {
                return;
            }

            $relativeClass = substr($class, strlen($namespace));
            $file = $path . '/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    /**
     * Boot all loaded plugins.
     */
    public function bootPlugins(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->plugins as $pluginId => $plugin) {
            try {
                $plugin->boot();

                // Register event listeners
                $this->registerEventListeners($plugin);

            } catch (Throwable $e) {
                Log::error("Failed to boot plugin: {$pluginId}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->booted = true;
    }

    /**
     * Register plugin event listeners.
     */
    private function registerEventListeners(PluginInterface $plugin): void
    {
        $listeners = $plugin->getEventListeners();

        foreach ($listeners as $event => $listener) {
            if (is_array($listener)) {
                $this->app['events']->listen($event, $listener);
            } else {
                $this->app['events']->listen($event, $listener);
            }
        }
    }

    /**
     * Get all scheduled tasks from plugins.
     *
     * @return array<int, array{plugin: string, job?: class-string, command?: string, frequency: string, args?: array}>
     */
    public function getScheduledTasks(): array
    {
        $tasks = [];

        foreach ($this->plugins as $pluginId => $plugin) {
            foreach ($plugin->getScheduledTasks() as $task) {
                $task['plugin'] = $pluginId;
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    /**
     * Get a loaded plugin instance.
     */
    public function get(string $pluginId): ?PluginInterface
    {
        return $this->plugins[$pluginId] ?? null;
    }

    /**
     * Get all loaded plugins.
     *
     * @return array<string, PluginInterface>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * Get all discovered plugin metadata.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllMetadata(): array
    {
        if (empty($this->metadata)) {
            $this->discover();
        }

        return $this->metadata;
    }

    /**
     * Get metadata for a specific plugin.
     *
     * @return array<string, mixed>|null
     */
    public function getMetadata(string $pluginId): ?array
    {
        return $this->getAllMetadata()[$pluginId] ?? null;
    }

    /**
     * Check if a plugin is loaded.
     */
    public function isLoaded(string $pluginId): bool
    {
        return isset($this->plugins[$pluginId]);
    }

    /**
     * Get count of loaded plugins.
     */
    public function count(): int
    {
        return count($this->plugins);
    }
}
