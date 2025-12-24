<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class TranslationsSyncCommand extends Command
{
    protected $signature = 'translations:sync {--dry-run : Show what would be done without making changes}';

    protected $description = 'Sync translation keys from database records to language files';

    private array $translationKeys = [];

    private array $supportedLocales = ['en', 'es'];

    private array $translatableModels = [
        'karma_events' => ['name', 'description'],
        'achievements' => ['name', 'description'],
    ];

    public function handle(): int
    {
        $this->info('Starting translation sync...');

        // Collect translation keys from database
        $this->collectTranslationKeys();

        // Update language files
        $this->updateLanguageFiles();

        $this->info('Translation sync completed!');

        return 0;
    }

    private function collectTranslationKeys(): void
    {
        $this->info('Collecting translation keys from database...');

        foreach ($this->translatableModels as $table => $columns) {
            if (! $this->tableExists($table)) {
                $this->warn("Table {$table} does not exist, skipping...");

                continue;
            }

            $records = DB::table($table)->get();

            foreach ($records as $record) {
                foreach ($columns as $column) {
                    $value = $record->{$column} ?? null;

                    if ($value && $this->isTranslationKey($value)) {
                        $this->addTranslationKey($value);
                    }
                }
            }
        }

        $this->info('Found ' . count($this->translationKeys) . ' translation keys');
    }

    private function isTranslationKey(string $value): bool
    {
        return Str::contains($value, '.') && ! Str::contains($value, ' ');
    }

    private function addTranslationKey(string $key): void
    {
        [$namespace, $translationKey] = explode('.', $key, 2);

        if (! isset($this->translationKeys[$namespace])) {
            $this->translationKeys[$namespace] = [];
        }

        $this->translationKeys[$namespace][] = $translationKey;
    }

    private function updateLanguageFiles(): void
    {
        foreach ($this->translationKeys as $namespace => $keys) {
            foreach ($this->supportedLocales as $locale) {
                $this->updateLanguageFile($namespace, $locale, $keys);
            }
        }
    }

    private function updateLanguageFile(string $namespace, string $locale, array $keys): void
    {
        $filePath = base_path("lang/{$locale}/{$namespace}.php");

        // Load existing translations
        $existingTranslations = [];
        if (File::exists($filePath)) {
            $existingTranslations = include $filePath;
        }

        // Add missing keys
        $newKeys = [];
        foreach ($keys as $key) {
            if (! isset($existingTranslations[$key])) {
                $newKeys[$key] = $this->generatePlaceholderTranslation($key, $locale);
            }
        }

        if (empty($newKeys)) {
            $this->line("No new keys for {$locale}/{$namespace}.php");

            return;
        }

        if ($this->option('dry-run')) {
            $this->warn('Would add ' . count($newKeys) . " keys to {$locale}/{$namespace}.php:");
            foreach ($newKeys as $key => $value) {
                $this->line("  '{$key}' => '{$value}'");
            }

            return;
        }

        // Merge and save
        $allTranslations = array_merge($existingTranslations, $newKeys);
        ksort($allTranslations);

        $this->saveLanguageFile($filePath, $allTranslations);
        $this->info("Updated {$locale}/{$namespace}.php with " . count($newKeys) . ' new keys');
    }

    private function generatePlaceholderTranslation(string $key, string $locale): string
    {
        // Generate a human-readable placeholder based on the key
        $placeholder = Str::title(str_replace('_', ' ', $key));

        // Add locale-specific prefixes for identification
        $prefix = match ($locale) {
            'es' => '[ES] ',
            'en' => '[EN] ',
            default => "[{$locale}] ",
        };

        return $prefix . $placeholder . ' [NEEDS TRANSLATION]';
    }

    private function saveLanguageFile(string $filePath, array $translations): void
    {
        // Ensure directory exists
        File::ensureDirectoryExists(dirname($filePath));

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n";

        foreach ($translations as $key => $value) {
            $escapedKey = addslashes($key);
            $escapedValue = addslashes($value);
            $content .= "    '{$escapedKey}' => '{$escapedValue}',\n";
        }

        $content .= "];\n";

        File::put($filePath, $content);
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (Exception $e) {
            return false;
        }
    }
}
