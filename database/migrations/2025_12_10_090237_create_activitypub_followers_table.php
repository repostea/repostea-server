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
        Schema::create('activitypub_followers', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_id')->unique(); // Remote actor URI (e.g., https://mastodon.social/users/alice)
            $table->string('inbox_url'); // Where to send activities
            $table->string('shared_inbox_url')->nullable(); // Shared inbox for batch delivery
            $table->string('username')->nullable(); // @alice
            $table->string('domain'); // mastodon.social
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamp('followed_at');
            $table->timestamps();

            $table->index('domain');
            $table->index('followed_at');
        });

        // Track delivered activities to avoid duplicates
        Schema::create('activitypub_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('activity_id')->index(); // Our activity URI
            $table->string('target_inbox'); // Where we sent it
            $table->enum('status', ['pending', 'delivered', 'failed'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'target_inbox']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activitypub_deliveries');
        Schema::dropIfExists('activitypub_followers');
    }
};
