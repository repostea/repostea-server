<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-actor ActivityPub tables.
 *
 * Implements FEP-1b12 (Group federation) with Lemmy-style naming:
 * - Users: @username@domain (Person)
 * - Groups: !groupname@domain (Group)
 * - Instance: @instance@domain (Application)
 *
 * These tables are separate from core tables (users, subs, posts)
 * to keep federation concerns isolated.
 */
return new class() extends Migration
{
    public function up(): void
    {
        // 1. Central actor registry
        // Each user/sub that opts into federation gets an actor here
        Schema::create('activitypub_actors', function (Blueprint $table): void {
            $table->id();

            // Actor classification
            $table->enum('actor_type', ['instance', 'user', 'group']);
            $table->enum('activitypub_type', ['Application', 'Person', 'Group']);

            // Link to local entity (null for instance actor)
            $table->unsignedBigInteger('entity_id')->nullable();

            // ActivityPub identity
            $table->string('username', 100); // Local username (juan, tecnologia)
            $table->string('preferred_username', 100); // For display
            $table->string('name')->nullable(); // Display name
            $table->text('summary')->nullable(); // Bio/description (HTML allowed)
            $table->string('icon_url')->nullable(); // Avatar

            // URIs (cached for performance)
            $table->string('actor_uri')->unique(); // https://domain/activitypub/users/juan
            $table->string('inbox_uri'); // https://domain/activitypub/users/juan/inbox
            $table->string('outbox_uri'); // https://domain/activitypub/users/juan/outbox
            $table->string('followers_uri'); // https://domain/activitypub/users/juan/followers

            // State
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique(['actor_type', 'entity_id']);
            $table->index(['actor_type', 'username']);
            $table->index('is_active');
        });

        // 2. RSA keys per actor (separate for security)
        Schema::create('activitypub_actor_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')
                ->constrained('activitypub_actors')
                ->onDelete('cascade');
            $table->text('public_key');
            $table->text('private_key'); // Encrypted at rest by Laravel
            $table->string('key_id'); // actor_uri#main-key
            $table->timestamps();

            $table->unique('actor_id');
        });

        // 3. Followers per actor
        // Different from activitypub_followers (which is for instance actor only)
        Schema::create('activitypub_actor_followers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')
                ->constrained('activitypub_actors')
                ->onDelete('cascade');

            // Remote follower info
            $table->string('follower_uri'); // https://mastodon.social/users/alice
            $table->string('follower_inbox');
            $table->string('follower_shared_inbox')->nullable();
            $table->string('follower_username')->nullable();
            $table->string('follower_domain');
            $table->string('follower_name')->nullable();
            $table->string('follower_icon')->nullable();

            $table->timestamp('followed_at');
            $table->timestamps();

            $table->unique(['actor_id', 'follower_uri']);
            $table->index(['actor_id', 'follower_domain']);
        });

        // 4. User federation settings
        // Opt-in configuration per user
        Schema::create('activitypub_user_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Opt-in to federation
            $table->boolean('federation_enabled')->default(false);
            $table->timestamp('federation_enabled_at')->nullable();

            // Default behavior for new posts
            $table->boolean('default_federate_posts')->default(false);

            // Discoverability
            $table->boolean('indexable')->default(true); // Show in searches
            $table->boolean('show_followers_count')->default(true);

            $table->timestamps();

            $table->unique('user_id');
        });

        // 5. Post federation settings
        // Per-post federation control
        Schema::create('activitypub_post_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')
                ->constrained()
                ->onDelete('cascade');

            // Author's choice
            $table->boolean('should_federate')->default(false);

            // Federation state
            $table->boolean('is_federated')->default(false);
            $table->timestamp('federated_at')->nullable();

            // ActivityPub references
            $table->string('note_uri')->nullable(); // Our Note URI
            $table->string('activity_uri')->nullable(); // Create activity URI

            $table->timestamps();

            $table->unique('post_id');
            $table->index(['should_federate', 'is_federated']);
        });

        // 6. Sub/Group federation settings
        // Per-sub federation control (Phase 2)
        Schema::create('activitypub_sub_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sub_id')
                ->constrained()
                ->onDelete('cascade');

            // Moderator opt-in
            $table->boolean('federation_enabled')->default(false);
            $table->timestamp('federation_enabled_at')->nullable();

            // Behavior
            $table->boolean('auto_announce')->default(true); // Auto-announce federable posts
            $table->boolean('accept_remote_posts')->default(false); // Future: accept posts from fediverse

            $table->timestamps();

            $table->unique('sub_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activitypub_sub_settings');
        Schema::dropIfExists('activitypub_post_settings');
        Schema::dropIfExists('activitypub_user_settings');
        Schema::dropIfExists('activitypub_actor_followers');
        Schema::dropIfExists('activitypub_actor_keys');
        Schema::dropIfExists('activitypub_actors');
    }
};
