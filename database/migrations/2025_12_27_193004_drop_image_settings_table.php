<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop image_settings table - sizes are now hardcoded in ImageService.
 *
 * IPX on the frontend handles image resizing, so we only store one size
 * per image and don't need configurable size settings anymore.
 */
return new class() extends Migration
{
    protected $connection = 'media';

    public function up(): void
    {
        Schema::connection('media')->dropIfExists('image_settings');
    }

    public function down(): void
    {
        Schema::connection('media')->create('image_settings', static function (Blueprint $table): void {
            $table->id();
            $table->enum('image_type', ['avatar', 'thumbnail', 'inline', 'sub_icon']);
            $table->enum('size_name', ['small', 'medium', 'large']);
            $table->integer('width');
            $table->unique(['image_type', 'size_name']);
            $table->timestamps();
        });

        // Insert default sizes
        DB::connection('media')->table('image_settings')->insert([
            // Avatar sizes
            ['image_type' => 'avatar', 'size_name' => 'small', 'width' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['image_type' => 'avatar', 'size_name' => 'medium', 'width' => 400, 'created_at' => now(), 'updated_at' => now()],
            ['image_type' => 'avatar', 'size_name' => 'large', 'width' => 800, 'created_at' => now(), 'updated_at' => now()],

            // Thumbnail sizes
            ['image_type' => 'thumbnail', 'size_name' => 'small', 'width' => 360, 'created_at' => now(), 'updated_at' => now()],
            ['image_type' => 'thumbnail', 'size_name' => 'medium', 'width' => 640, 'created_at' => now(), 'updated_at' => now()],
            ['image_type' => 'thumbnail', 'size_name' => 'large', 'width' => 1280, 'created_at' => now(), 'updated_at' => now()],

            // Inline images
            ['image_type' => 'inline', 'size_name' => 'small', 'width' => 430, 'created_at' => now(), 'updated_at' => now()],
            ['image_type' => 'inline', 'size_name' => 'medium', 'width' => 860, 'created_at' => now(), 'updated_at' => now()],
            ['image_type' => 'inline', 'size_name' => 'large', 'width' => 1920, 'created_at' => now(), 'updated_at' => now()],

            // Sub icon sizes
            ['image_type' => 'sub_icon', 'size_name' => 'small', 'width' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['image_type' => 'sub_icon', 'size_name' => 'medium', 'width' => 400, 'created_at' => now(), 'updated_at' => now()],
            ['image_type' => 'sub_icon', 'size_name' => 'large', 'width' => 800, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
