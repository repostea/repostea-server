<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $environment = app()->environment();
        $forceDev = env('SEED_DEV_DATA', false);

        if ($forceDev && $environment === 'production') {
            $this->command->warn('⚠️  Running development seeders in production (SEED_DEV_DATA=true)');
            $this->command->warn('   This will create fake users, posts, comments, and votes.');
            $this->seedDevelopmentData();

            return;
        }

        if ($environment === 'testing') {
            $this->seedMinimalTestData();
        } elseif ($environment === 'production') {
            $this->seedProductionData();
        } else {
            $this->seedDevelopmentData();
        }
    }

    private function seedProductionData(): void
    {
        $this->command->info('Production mode: structural data is loaded via migrations.');
        $this->command->info('To seed test data, run: SEED_DEV_DATA=true php artisan db:seed');
    }

    private function seedMinimalTestData(): void
    {
        User::factory()->create([
            'username' => 'test-user',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        User::factory()->admin()->create([
            'username' => 'test-admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->call([
            AIPostsSeeder::class,
            CommentsSeeder::class,
            VotesSeeder::class,
        ]);

        $this->command->info('Minimal test data loaded successfully.');
    }

    private function seedDevelopmentData(): void
    {
        $this->call([
            KarmaEventsSeeder::class,
            UserStreaksSeeder::class,
            TagCategoriesAndTagsSeeder::class,
        ]);

        $this->createDevelopmentUsers();

        $this->call([
            AIPostsSeeder::class,
            CommentsSeeder::class,
            VotesSeeder::class,
        ]);

        $this->command->info('Development data loaded successfully.');
    }

    private function createDevelopmentUsers(): void
    {
        $users = User::factory()->count(15)->create();

        User::factory()->expert()->count(5)->create();

        if (! User::where('email', env('ADMIN_EMAIL', 'admin@example.com'))->exists()) {
            User::factory()->admin()->create([
                'username' => env('ADMIN_USERNAME', 'admin'),
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'changeme123')),
            ]);
        }

        $this->command->info('Development users created successfully.');
    }
}
