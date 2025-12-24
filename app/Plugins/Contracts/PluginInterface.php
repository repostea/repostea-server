<?php

declare(strict_types=1);

namespace App\Plugins\Contracts;

/**
 * Interface that all Repostea plugins must implement.
 *
 * Plugins extend Repostea functionality without modifying core code.
 * They can register services, listen to events, add commands, and more.
 */
interface PluginInterface
{
    /**
     * Get the unique identifier for this plugin.
     * Should match the plugin directory name.
     */
    public function getId(): string;

    /**
     * Get human-readable plugin name.
     */
    public function getName(): string;

    /**
     * Get plugin description.
     */
    public function getDescription(): string;

    /**
     * Get plugin version.
     */
    public function getVersion(): string;

    /**
     * Register plugin services, bindings, and configurations.
     * Called before boot, use for service container bindings.
     */
    public function register(): void;

    /**
     * Bootstrap the plugin.
     * Called after all plugins are registered.
     * Use for event listeners, observers, routes, etc.
     */
    public function boot(): void;

    /**
     * Get scheduled tasks for this plugin.
     * Returns array of task definitions.
     *
     * Example:
     * [
     *     ['job' => MyJob::class, 'frequency' => 'everyFiveMinutes'],
     *     ['command' => 'plugin:sync', 'frequency' => 'hourly'],
     * ]
     *
     * @return array<int, array{job?: class-string, command?: string, frequency: string, args?: array}>
     */
    public function getScheduledTasks(): array;

    /**
     * Get artisan commands provided by this plugin.
     *
     * @return array<int, class-string>
     */
    public function getCommands(): array;

    /**
     * Get event listeners for this plugin.
     *
     * Example:
     * [
     *     PostCreated::class => [HandleNewPost::class, 'handle'],
     *     UserRegistered::class => NotifyAdmin::class,
     * ]
     *
     * @return array<class-string, class-string|array>
     */
    public function getEventListeners(): array;

    /**
     * Check if plugin dependencies are satisfied.
     * Return true if all dependencies are met.
     */
    public function checkDependencies(): bool;

    /**
     * Get plugin dependencies (other plugin IDs).
     *
     * @return array<int, string>
     */
    public function getDependencies(): array;

    /**
     * Called when plugin is being disabled.
     * Use for cleanup tasks.
     */
    public function disable(): void;
}
