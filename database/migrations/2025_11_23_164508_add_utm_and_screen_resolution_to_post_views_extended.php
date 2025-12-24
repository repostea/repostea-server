<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('post_views_extended', 'utm_source')) {
            return;
        }

        Schema::table('post_views_extended', function (Blueprint $table): void {
            // UTM parameters for marketing campaign tracking
            $table->string('utm_source', 100)->nullable()->after('referer');
            $table->string('utm_medium', 100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 100)->nullable()->after('utm_medium');
            $table->string('utm_term', 100)->nullable()->after('utm_campaign');
            $table->string('utm_content', 100)->nullable()->after('utm_term');

            // Screen resolution for device analytics
            $table->string('screen_resolution', 20)->nullable()->after('utm_content'); // e.g., "1920x1080"

            // Indexes for analytics queries
            $table->index('utm_source');
            $table->index('utm_medium');
            $table->index('utm_campaign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_views_extended', function (Blueprint $table): void {
            $table->dropIndex(['utm_source']);
            $table->dropIndex(['utm_medium']);
            $table->dropIndex(['utm_campaign']);

            $table->dropColumn([
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'screen_resolution',
            ]);
        });
    }
};
