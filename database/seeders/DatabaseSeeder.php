<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\KarmaEvent;
use App\Models\KarmaHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $environment = app()->environment();

        if ($environment === 'testing') {
            $this->seedMinimalTestData();
        } else {
            // In production and development, run the same seeders
            $this->seedDevelopmentData();
        }
    }

    private function seedStructuralData(): void
    {
        $this->call([
            RoleSeeder::class,
            // KarmaLevelSeeder::class, // Moved to migration: 2025_10_25_213108_seed_karma_levels_data.php
            // AchievementsSeeder::class, // Moved to migration: 2025_10_25_210429_seed_achievements_data.php
        ]);

        if (! User::where('email', env('ADMIN_EMAIL', 'admin@example.com'))->exists()) {
            $user = User::create([
                'username' => env('ADMIN_USERNAME', 'admin'),
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'changeme123')),
                'email_verified_at' => now(),
                'karma_points' => 5000,
            ]);
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole) {
                $user->roles()->attach($adminRole);
            }
        }

        $this->command->info('Structural data loaded successfully.');
    }

    private function seedMinimalTestData(): void
    {
        $this->seedStructuralData();

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
        $this->seedStructuralData();

        $this->call([
            KarmaEventsSeeder::class,
            AggregationSourcesSeeder::class,
            UserStreaksSeeder::class,
            TagCategoriesAndTagsSeeder::class,
        ]);

        $this->createDevelopmentUsers();

        $this->call([
            AIPostsSeeder::class,
            CommentsSeeder::class,
            VotesSeeder::class,
        ]);

        $this->createKarmaHistoryAndEvents();

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

    private function createKarmaHistoryAndEvents(): void
    {
        User::all()->each(function ($user): void {
            KarmaHistory::factory()
                ->count(rand(5, 15))
                ->create(['user_id' => $user->id]);
        });

        KarmaEvent::factory()->active()->create();
        KarmaEvent::factory()->upcoming()->count(2)->create();

        $this->command->info('Karma history and additional events created successfully.');
    }
}
