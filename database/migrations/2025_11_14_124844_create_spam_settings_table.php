<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('spam_settings')) {
            return;
        }

        Schema::create('spam_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->string('type')->default('string'); // string, integer, float, boolean, json
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        DB::table('spam_settings')->insert([
            [
                'key' => 'duplicate_detection_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable duplicate content detection',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'duplicate_similarity_threshold',
                'value' => '0.85',
                'type' => 'float',
                'description' => 'Similarity threshold for duplicate detection (0.0 to 1.0)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'duplicate_check_hours',
                'value' => '24',
                'type' => 'integer',
                'description' => 'Hours to look back for duplicate content',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'spam_score_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable spam score calculation',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'spam_score_threshold',
                'value' => '70',
                'type' => 'integer',
                'description' => 'Spam score threshold for flagging (0 to 100)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'rapid_fire_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable rapid-fire posting detection',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'rapid_fire_posts_limit',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Maximum posts allowed in rapid fire window',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'rapid_fire_minutes',
                'value' => '10',
                'type' => 'integer',
                'description' => 'Minutes for rapid-fire detection window',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'auto_hide_duplicates',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Automatically hide duplicate content',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'auto_hide_high_spam_score',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Automatically hide content with high spam score',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spam_settings');
    }
};
