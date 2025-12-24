<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Tag categories table
        Schema::create('tag_categories', static function (Blueprint $table): void {
            $table->id();
            $table->string('name_key');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Tags table
        Schema::create('tags', static function (Blueprint $table): void {
            $table->id();
            $table->string('name_key');
            $table->string('slug')->unique();
            $table->text('description_key')->nullable();
            $table->foreignId('tag_category_id')->nullable()->constrained('tag_categories')->onDelete('set null');
            $table->timestamps();
        });

        // Posts-tags pivot table
        Schema::create('post_tag', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('tag_categories');
    }
};
