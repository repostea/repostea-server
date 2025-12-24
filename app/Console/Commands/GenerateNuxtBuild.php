<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class GenerateNuxtBuild extends Command
{
    /**
     * The name and signature of the command.
     *
     * @var string
     */
    protected $signature = 'nuxt:generate {--api : Run npm run generate:api instead of generate:local}';

    /**
     * The description of the command.
     */
    protected $description = 'Generate Nuxt static build and copy it to public/app';

    /**
     * Execute the command.
     */
    public function handle()
    {
        $this->info('Starting Nuxt generation process...');

        // Verify that the Nuxt project folder exists
        $nuxtPath = base_path('client');
        if (! File::isDirectory($nuxtPath)) {
            $this->error("Nuxt project folder not found at: {$nuxtPath}");

            return 1;
        }

        // Change to the Nuxt project directory
        $this->info("Changing to directory: {$nuxtPath}");
        chdir($nuxtPath);

        // Determine which command to run based on the --api option
        $generateCommand = $this->option('api') ? 'generate:api' : 'generate:local';

        // Run the npm command
        $this->info("Running: npm run {$generateCommand}");
        $this->newLine();

        // Use Process to run npm safely with real-time output
        $process = new Process(['npm', 'run', $generateCommand], $nuxtPath);
        $process->setTimeout(600); // 10 minutes for build
        $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error("Error running npm run {$generateCommand}");

            return 1;
        }

        $this->newLine();
        $this->info('Build generated successfully');

        // Verify that the output folder exists
        $outputDir = "{$nuxtPath}/dist/app";
        if (! File::isDirectory($outputDir)) {
            $this->error("Output folder not found at: {$outputDir}");

            return 1;
        }

        // Create app folder in public if it doesn't exist
        $publicAppDir = public_path('app');
        if (! File::isDirectory($publicAppDir)) {
            $this->info("Creating directory: {$publicAppDir}");
            File::makeDirectory($publicAppDir, 0755, true);
        } else {
            // Clean the directory before copying new files
            $this->info("Cleaning existing directory: {$publicAppDir}");
            File::cleanDirectory($publicAppDir);
        }

        // Copy files
        $this->info("Copying files from {$outputDir} to {$publicAppDir}");
        File::copyDirectory($outputDir, $publicAppDir);

        $this->info('Nuxt build generated and copied successfully!');

        return 0;
    }
}
