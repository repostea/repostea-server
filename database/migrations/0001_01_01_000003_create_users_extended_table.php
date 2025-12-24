<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            // Karma system (foreign key will be added later)
            $table->integer('karma_points')->default(0);
            $table->unsignedBigInteger('highest_level_id')->nullable();
            $table->string('locale')->default('es');

            // Profile fields
            $table->text('bio')->nullable()->after('email');
            $table->string('display_name')->nullable()->after('username');
            $table->string('avatar')->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('settings')->nullable();

            // Expert system
            $table->boolean('is_verified_expert')->default(false);
            $table->string('expertise_areas')->nullable();
            $table->text('credentials')->nullable();
            $table->string('institution')->nullable();
            $table->string('professional_title')->nullable();
            $table->string('academic_degree')->nullable();
            $table->text('publications')->nullable();

            // Two factor authentication
            $table->text('two_factor_secret')->after('password')->nullable();
            $table->text('two_factor_recovery_codes')->after('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->after('two_factor_recovery_codes')->nullable();

            // Guest users
            $table->boolean('is_guest')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn([
                'karma_points',
                'highest_level_id',
                'locale',
                'bio',
                'display_name',
                'avatar',
                'avatar_url',
                'settings',
                'is_verified_expert',
                'expertise_areas',
                'credentials',
                'institution',
                'professional_title',
                'academic_degree',
                'publications',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'is_guest',
            ]);
        });
    }
};
