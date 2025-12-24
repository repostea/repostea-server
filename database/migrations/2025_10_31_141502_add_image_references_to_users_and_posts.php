<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Add column to users only if it doesn't exist
        if (! Schema::hasColumn('users', 'avatar_image_id')) {
            Schema::table('users', static function (Blueprint $table): void {
                // Add reference to images table (no foreign key because images is in different database)
                // Keep old avatar columns for backward compatibility temporarily
                $table->unsignedBigInteger('avatar_image_id')->nullable()->after('avatar_url');
                $table->index('avatar_image_id');
            });
        }

        // Add column to posts only if it doesn't exist
        if (! Schema::hasColumn('posts', 'thumbnail_image_id')) {
            Schema::table('posts', static function (Blueprint $table): void {
                // Add reference to images table (no foreign key because images is in different database)
                // Keep old thumbnail_url for backward compatibility temporarily
                $table->unsignedBigInteger('thumbnail_image_id')->nullable()->after('thumbnail_url');
                $table->index('thumbnail_image_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropIndex(['avatar_image_id']);
            $table->dropColumn('avatar_image_id');
        });

        Schema::table('posts', static function (Blueprint $table): void {
            $table->dropIndex(['thumbnail_image_id']);
            $table->dropColumn('thumbnail_image_id');
        });
    }
};
