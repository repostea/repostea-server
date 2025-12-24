<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Minimal seeder for E2E tests.
 * Only includes essential data needed for tests to run.
 */
final class E2ESeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            KarmaLevelSeeder::class,
            AchievementsSeeder::class,
            CategorySeeder::class,
            TagCategoriesAndTagsSeeder::class,
        ]);
    }
}
