<?php

declare(strict_types=1);

namespace App\Plugins;

use const DIRECTORY_SEPARATOR;

use App\Plugins\Contracts\PluginInterface;
use ReflectionClass;

/**
 * Base class for Repostea plugins.
 *
 * Extend this class to create a plugin with sensible defaults.
 * Override methods as needed for your plugin's functionality.
 */
abstract class BasePlugin implements PluginInterface
{
    /**
     * Plugin metadata loaded from plugin.json.
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Plugin base path.
     */
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = $this->resolveBasePath();
        $this->loadMetadata();
        $this->loadConfig();
    }

    public function getId(): string
    {
        return $this->metadata['id'] ?? basename($this->basePath);
    }

    public function getName(): string
    {
        return $this->metadata['name'] ?? $this->getId();
    }

    public function getDescription(): string
    {
        return $this->metadata['description'] ?? '';
    }

    public function getVersion(): string
    {
        return $this->metadata['version'] ?? '1.0.0';
    }

    public function register(): void
    {
        // Override in child class
    }

    public function boot(): void
    {
        // Override in child class
    }

    public function getScheduledTasks(): array
    {
        return [];
    }

    public function getCommands(): array
    {
        return [];
    }

    public function getEventListeners(): array
    {
        return [];
    }

    public function checkDependencies(): bool
    {
        foreach ($this->getDependencies() as $dependency) {
            if (! app(PluginManager::class)->isEnabled($dependency)) {
                return false;
            }
        }

        return true;
    }

    public function getDependencies(): array
    {
        return $this->metadata['dependencies'] ?? [];
    }

    public function disable(): void
    {
        // Override in child class for cleanup
    }

    /**
     * Get the plugin's base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get a path relative to the plugin's base path.
     */
    public function path(string $relativePath = ''): string
    {
        return $this->basePath . ($relativePath ? DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\') : '');
    }

    /**
     * Get plugin configuration value.
     *
     * @param  null|mixed  $default
     */
    public function config(string $key, $default = null)
    {
        return config("plugins.{$this->getId()}.{$key}", $default);
    }

    /**
     * Resolve the base path of the plugin.
     */
    protected function resolveBasePath(): string
    {
        $reflection = new ReflectionClass($this);
        $filename = $reflection->getFileName();

        return $filename ? dirname($filename) : '';
    }

    /**
     * Load plugin metadata from plugin.json.
     */
    protected function loadMetadata(): void
    {
        $metadataFile = $this->path('plugin.json');

        if (file_exists($metadataFile)) {
            $content = file_get_contents($metadataFile);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $this->metadata = $decoded;
                }
            }
        }
    }

    /**
     * Load plugin configuration from config.php and merge into app config.
     */
    protected function loadConfig(): void
    {
        $configFile = $this->path('config.php');

        if (file_exists($configFile)) {
            $pluginConfig = require $configFile;
            if (is_array($pluginConfig)) {
                $key = "plugins.{$this->getId()}";
                $existing = config($key, []);
                config([$key => array_merge($pluginConfig, $existing)]);
            }
        }
    }
}
