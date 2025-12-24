<?php

declare(strict_types=1);

namespace App\Plugins\Console;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

use App\Plugins\PluginManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Command to scaffold a new plugin.
 */
final class PluginMakeCommand extends Command
{
    protected $signature = 'plugin:make
                            {name : The plugin name (slug format, e.g., my-awesome-plugin)}
                            {--description= : Plugin description}
                            {--author= : Plugin author name}';

    protected $description = 'Create a new plugin scaffold';

    public function handle(PluginManager $pluginManager): int
    {
        $name = $this->argument('name');
        $description = $this->option('description') ?? 'A Repostea plugin';
        $author = $this->option('author') ?? 'Unknown';

        // Validate name
        if (! preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            $this->error('Plugin name must be lowercase, start with a letter, and contain only letters, numbers, and hyphens.');

            return self::FAILURE;
        }

        $pluginPath = $pluginManager->getPluginsPath() . '/' . $name;

        // Check if already exists
        if (File::isDirectory($pluginPath)) {
            $this->error("Plugin '{$name}' already exists at: {$pluginPath}");

            return self::FAILURE;
        }

        // Create directories
        File::makeDirectory($pluginPath, 0755, true);
        File::makeDirectory($pluginPath . '/Services', 0755, true);
        File::makeDirectory($pluginPath . '/Jobs', 0755, true);
        File::makeDirectory($pluginPath . '/Commands', 0755, true);

        // Generate class name
        $className = Str::studly(str_replace('-', ' ', $name)) . 'Plugin';
        $namespace = 'Plugins\\' . Str::studly(str_replace('-', ' ', $name));

        // Create plugin.json
        $metadata = [
            'id' => $name,
            'name' => Str::title(str_replace('-', ' ', $name)),
            'description' => $description,
            'version' => '1.0.0',
            'author' => $author,
            'main' => $namespace . '\\' . $className,
            'namespace' => $namespace . '\\',
            'dependencies' => [],
        ];

        File::put(
            $pluginPath . '/plugin.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        // Create main plugin class
        $pluginClass = $this->generatePluginClass($className, $namespace);
        File::put($pluginPath . '/' . $className . '.php', $pluginClass);

        // Create example service
        $serviceClass = $this->generateServiceClass($name, $namespace);
        File::put($pluginPath . '/Services/' . Str::studly(str_replace('-', ' ', $name)) . 'Service.php', $serviceClass);

        // Create README
        $readme = $this->generateReadme($name, $description);
        File::put($pluginPath . '/README.md', $readme);

        $this->info("Plugin '{$name}' created successfully at: {$pluginPath}");
        $this->newLine();
        $this->info('Next steps:');
        $this->line("1. Edit your plugin at: {$pluginPath}/{$className}.php");
        $this->line('2. Enable the plugin by adding to .env: ENABLED_PLUGINS=' . $name);
        $this->line('3. Clear config cache: php artisan config:clear');

        return self::SUCCESS;
    }

    private function generatePluginClass(string $className, string $namespace): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use App\Plugins\BasePlugin;

/**
 * Main plugin class.
 */
final class {$className} extends BasePlugin
{
    /**
     * Register plugin services.
     */
    public function register(): void
    {
        // Register services, bindings, etc.
        // Example:
        // app()->bind(MyServiceInterface::class, MyService::class);
    }

    /**
     * Bootstrap the plugin.
     */
    public function boot(): void
    {
        // Initialize plugin, register listeners, etc.
    }

    /**
     * Get scheduled tasks.
     */
    public function getScheduledTasks(): array
    {
        return [
            // Example:
            // ['job' => Jobs\MyJob::class, 'frequency' => 'everyFiveMinutes'],
        ];
    }

    /**
     * Get artisan commands.
     */
    public function getCommands(): array
    {
        return [
            // Example:
            // Commands\MyCommand::class,
        ];
    }

    /**
     * Get event listeners.
     */
    public function getEventListeners(): array
    {
        return [
            // Example:
            // \App\Events\PostCreated::class => Listeners\HandleNewPost::class,
        ];
    }
}
PHP;
    }

    private function generateServiceClass(string $name, string $namespace): string
    {
        $serviceName = Str::studly(str_replace('-', ' ', $name)) . 'Service';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Services;

/**
 * Main service for the plugin.
 */
final class {$serviceName}
{
    /**
     * Example method.
     */
    public function doSomething(): void
    {
        // Implement your logic here
    }
}
PHP;
    }

    private function generateReadme(string $name, string $description): string
    {
        $title = Str::title(str_replace('-', ' ', $name));

        return <<<MD
# {$title}

{$description}

## Installation

1. Place this plugin in `server/plugins/{$name}/`
2. Add to your `.env` file: `ENABLED_PLUGINS={$name}`
3. Clear config cache: `php artisan config:clear`

## Configuration

Add any configuration to `config/plugins.php`:

```php
'{$name}' => [
    'option1' => env('PLUGIN_OPTION1', 'default'),
],
```

## Usage

Describe how to use your plugin here.

## License

MIT
MD;
    }
}
