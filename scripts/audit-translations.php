#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Laravel Translation Audit Script.
 *
 * This script:
 * 1. Scans all PHP/Blade files for translation keys used in __(), trans(), @lang
 * 2. Loads all translation files for each locale
 * 3. Compares used keys with available translations
 * 4. Reports missing translations
 */
$projectRoot = dirname(__DIR__);
$langDir = $projectRoot . '/lang';
$sourceDir = $projectRoot;

// File extensions to scan
$extensions = ['.php', '.blade.php'];

// Regex patterns to find translation keys
$translationPatterns = [
    '/__\([\'"]([^\'"]+)[\'"]\)/',
    '/trans\([\'"]([^\'"]+)[\'"]\)/',
    '/@lang\([\'"]([^\'"]+)[\'"]\)/',
    '/trans_choice\([\'"]([^\'"]+)[\'"]\)/',
];

/**
 * Recursively get all files with specific extensions.
 */
function getAllFiles($dir, &$fileList = [])
{
    $extensions = ['.php', '.blade.php'];

    if (! is_dir($dir)) {
        return $fileList;
    }

    $files = scandir($dir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath = $dir . '/' . $file;

        if (is_dir($filePath)) {
            // Skip certain directories
            if (in_array($file, ['vendor', 'node_modules', 'storage', 'bootstrap', '.git'])) {
                continue;
            }
            getAllFiles($filePath, $fileList);
        } else {
            foreach ($extensions as $ext) {
                if (str_ends_with($file, $ext)) {
                    $fileList[] = $filePath;
                    break;
                }
            }
        }
    }

    return $fileList;
}

/**
 * Extract translation keys from file content.
 */
function extractKeysFromFile($filePath)
{
    global $translationPatterns;
    $keys = [];

    $content = file_get_contents($filePath);

    foreach ($translationPatterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $key) {
                // Skip dynamic keys (variables)
                if (! str_contains($key, '$') && ! str_contains($key, '{')) {
                    $keys[] = $key;
                }
            }
        }
    }

    return $keys;
}

/**
 * Load all translations for a locale.
 */
function loadLocaleTranslations($locale, $langDir)
{
    $translations = [];
    $localeDir = $langDir . '/' . $locale;

    if (! is_dir($localeDir)) {
        return $translations;
    }

    $files = scandir($localeDir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        if (str_ends_with($file, '.php')) {
            $filePath = $localeDir . '/' . $file;
            $namespace = str_replace('.php', '', $file);
            $content = include $filePath;

            if (is_array($content)) {
                $translations[$namespace] = $content;
            }
        } elseif (is_dir($localeDir . '/' . $file)) {
            // Handle nested directories
            $nestedFiles = scandir($localeDir . '/' . $file);
            foreach ($nestedFiles as $nestedFile) {
                if ($nestedFile === '.' || $nestedFile === '..') {
                    continue;
                }

                if (str_ends_with($nestedFile, '.php')) {
                    $filePath = $localeDir . '/' . $file . '/' . $nestedFile;
                    $namespace = $file . '.' . str_replace('.php', '', $nestedFile);
                    $content = include $filePath;

                    if (is_array($content)) {
                        $translations[$namespace] = $content;
                    }
                }
            }
        }
    }

    return $translations;
}

/**
 * Get nested value from array using dot notation.
 */
function getNestedValue($array, $path)
{
    $keys = explode('.', $path);
    $value = $array;

    foreach ($keys as $key) {
        if (! isset($value[$key])) {
            return;
        }
        $value = $value[$key];
    }

    return $value;
}

/**
 * Main audit function.
 */
function auditTranslations(): void
{
    global $langDir, $sourceDir;

    echo "🔍 Scanning source files for translation keys...\n\n";

    // Get all source files
    $sourceFiles = getAllFiles($sourceDir);
    echo 'Found ' . count($sourceFiles) . " source files to scan\n\n";

    // Extract all used translation keys
    $usedKeys = [];
    foreach ($sourceFiles as $file) {
        $keys = extractKeysFromFile($file);
        $usedKeys = array_merge($usedKeys, $keys);
    }
    $usedKeys = array_unique($usedKeys);
    sort($usedKeys);

    echo 'Found ' . count($usedKeys) . " unique translation keys in source code\n\n";

    // Get available locales
    $locales = [];
    if (is_dir($langDir)) {
        $items = scandir($langDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_dir($langDir . '/' . $item)) {
                $locales[] = $item;
            }
        }
    }

    echo 'Found ' . count($locales) . ' locales: ' . implode(', ', $locales) . "\n\n";
    echo str_repeat('=', 80) . "\n";

    // Check each locale
    foreach ($locales as $locale) {
        echo "\n📋 Checking locale: {$locale}\n";
        echo str_repeat('-', 80) . "\n";

        $translations = loadLocaleTranslations($locale, $langDir);
        $missingKeys = [];

        foreach ($usedKeys as $key) {
            $value = getNestedValue($translations, $key);
            if ($value === null) {
                $missingKeys[] = $key;
            }
        }

        if (empty($missingKeys)) {
            echo '✅ All ' . count($usedKeys) . " translation keys are present!\n";
        } else {
            echo '❌ Missing ' . count($missingKeys) . " translations:\n\n";
            foreach ($missingKeys as $key) {
                echo "  - {$key}\n";
            }
        }
    }

    echo "\n" . str_repeat('=', 80) . "\n";
    echo "\n✨ Translation audit complete!\n\n";
}

// Run the audit
auditTranslations();
