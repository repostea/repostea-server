<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\KarmaLevel;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration cleans up old achievements and karma levels before the new system is applied.
     * It ensures a clean slate for the new karma and achievements system.
     */
    public function up(): void
    {
        // 1. Delete user relationships with old achievements
        DB::table('achievement_user')->truncate();

        // 2. Delete all old achievements
        Achievement::query()->delete();

        // 3. Save the required_karma of each user's current level
        // We do this in PHP to be compatible with MySQL and SQLite
        // This information will be used in the seed_karma_levels migration for comparison
        $oldLevelsData = DB::table('users')
            ->leftJoin('karma_levels', 'users.highest_level_id', '=', 'karma_levels.id')
            ->select('users.id as user_id', DB::raw('COALESCE(karma_levels.required_karma, 0) as old_level_karma'))
            ->get();

        // Save to a temporary JSON file
        file_put_contents(
            storage_path('app/old_karma_levels.json'),
            json_encode($oldLevelsData->keyBy('user_id')->toArray()),
        );

        // 4. Reset the highest_level_id of all users to NULL temporarily
        // This prevents references to levels that don't exist after deletion
        // Use DB::table() instead of User model to avoid SoftDeletes trait issues
        DB::table('users')->update([
            'highest_level_id' => null,
        ]);

        // 5. Delete all old karma levels
        // Now safe because users no longer reference them
        KarmaLevel::query()->delete();

        // 6. Reset auto-increment so new IDs start from 1
        // Only in MySQL (SQLite in tests doesn't support it)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE achievements AUTO_INCREMENT = 1');
            DB::statement('ALTER TABLE karma_levels AUTO_INCREMENT = 1');
        } elseif (DB::getDriverName() === 'sqlite') {
            // In SQLite it resets automatically when deleting all records
            // We don't need to do anything additional
        }
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible - this migration permanently cleans up old data.
     */
    public function down(): void
    {
        // Cannot reverse data deletion
        // Old data will be permanently lost
    }
};
