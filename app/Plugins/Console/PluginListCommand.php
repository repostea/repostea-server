<?php

declare(strict_types=1);

namespace App\Plugins\Console;

use App\Plugins\PluginManager;
use Illuminate\Console\Command;

/**
 * Command to list all available plugins.
 */
final class PluginListCommand extends Command
{
    protected $signature = 'plugin:list';

    protected $description = 'List all available plugins and their status';

    public function handle(PluginManager $pluginManager): int
    {
        $plugins = $pluginManager->getAllMetadata();

        if (empty($plugins)) {
            $this->info('No plugins found in ' . $pluginManager->getPluginsPath());
            $this->newLine();
            $this->info('Create a plugin with: php artisan plugin:make {name}');

            return self::SUCCESS;
        }

        $this->info('Available Plugins:');
        $this->newLine();

        $rows = [];
        foreach ($plugins as $id => $metadata) {
            $status = $metadata['enabled'] ? '<fg=green>Enabled</>' : '<fg=gray>Disabled</>';
            $loaded = $pluginManager->isLoaded($id) ? '<fg=green>Yes</>' : '<fg=gray>No</>';

            $rows[] = [
                $id,
                $metadata['name'] ?? $id,
                $metadata['version'] ?? '1.0.0',
                $status,
                $loaded,
                $metadata['description'] ?? '',
            ];
        }

        $this->table(
            ['ID', 'Name', 'Version', 'Status', 'Loaded', 'Description'],
            $rows,
        );

        $this->newLine();
        $this->info('To enable a plugin, add it to ENABLED_PLUGINS in your .env file:');
        $this->line('ENABLED_PLUGINS=plugin-id-1,plugin-id-2');

        return self::SUCCESS;
    }
}
