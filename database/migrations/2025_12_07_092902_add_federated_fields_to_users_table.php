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
        Schema::table('users', function (Blueprint $table): void {
            // Federated identity fields
            $table->string('federated_id')->nullable()->unique()->after('email');
            $table->string('federated_instance')->nullable()->after('federated_id');
            $table->string('federated_username')->nullable()->after('federated_instance');
            $table->timestamp('federated_account_created_at')->nullable()->after('federated_username');

            // Make email nullable for federated users
            $table->string('email')->nullable()->change();

            // Index for faster lookups
            $table->index(['federated_instance', 'federated_username']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['federated_instance', 'federated_username']);
            $table->dropColumn([
                'federated_id',
                'federated_instance',
                'federated_username',
                'federated_account_created_at',
            ]);
        });
    }
};
