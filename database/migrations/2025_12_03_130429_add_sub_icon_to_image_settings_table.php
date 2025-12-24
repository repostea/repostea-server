<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    protected $connection = 'media';

    public function up(): void
    {
        // Skip entire migration for SQLite (used in tests) - ENUM and CHECK constraints don't work the same
        $driver = DB::connection('media')->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        // Alter the ENUM to add 'sub_icon' type
        DB::connection('media')->statement("ALTER TABLE image_settings MODIFY image_type ENUM('avatar', 'thumbnail', 'inline', 'sub_icon')");

        // Insert sub_icon sizes (same as avatar - small icons)
        DB::connection('media')->table('image_settings')->insertOrIgnore([
            ['image_type' => 'sub_icon', 'size_name' => 'small', 'width' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['image_type' => 'sub_icon', 'size_name' => 'medium', 'width' => 400, 'created_at' => now(), 'updated_at' => now()],
            ['image_type' => 'sub_icon', 'size_name' => 'large', 'width' => 800, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        // Skip for SQLite
        $driver = DB::connection('media')->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        // Delete sub_icon settings
        DB::connection('media')->table('image_settings')->where('image_type', 'sub_icon')->delete();

        // Revert the ENUM
        DB::connection('media')->statement("ALTER TABLE image_settings MODIFY image_type ENUM('avatar', 'thumbnail', 'inline')");
    }
};
