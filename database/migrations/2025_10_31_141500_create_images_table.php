<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    protected $connection = 'media';

    public function up(): void
    {
        // Skip if table already exists
        if (Schema::connection('media')->hasTable('images')) {
            return;
        }

        Schema::connection('media')->create('images', static function (Blueprint $table): void {
            $table->id();

            // Unique hash for the image (used for cache filenames)
            $table->string('hash', 64)->unique();

            // Image type: avatar, thumbnail, inline, sub_icon
            $table->enum('type', ['avatar', 'thumbnail', 'inline', 'sub_icon']);

            // Polymorphic relation to owner (User, Post, Comment)
            $table->string('uploadable_type')->nullable();
            $table->unsignedBigInteger('uploadable_id')->nullable();
            $table->index(['uploadable_type', 'uploadable_id']);

            // Binary columns for image data (nullable since IPX now handles resizing)
            $table->binary('small_blob')->nullable();
            $table->binary('medium_blob')->nullable();
            $table->binary('large_blob')->nullable();

            // Image metadata
            $table->integer('original_width')->nullable();
            $table->integer('original_height')->nullable();
            $table->integer('file_size'); // Total size in bytes of all blobs
            $table->string('mime_type')->default('image/webp');

            // User who uploaded the image
            $table->unsignedBigInteger('user_id');
            $table->index('user_id');

            $table->timestamps();
            $table->softDeletes();
        });

        // Alter columns to MEDIUMBLOB (supports up to 16MB each) - only for MySQL
        $driver = Schema::connection('media')->getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::connection('media')->statement('ALTER TABLE images MODIFY small_blob MEDIUMBLOB');
            DB::connection('media')->statement('ALTER TABLE images MODIFY medium_blob MEDIUMBLOB');
            DB::connection('media')->statement('ALTER TABLE images MODIFY large_blob MEDIUMBLOB');
        }
    }

    public function down(): void
    {
        Schema::connection('media')->dropIfExists('images');
    }
};
