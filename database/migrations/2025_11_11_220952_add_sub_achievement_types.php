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
        Schema::table('achievements', function (Blueprint $table): void {
            $table->enum('type', [
                'karma',
                'posts',
                'post',
                'comments',
                'comment',
                'streak',
                'special',
                'action',
                'vote',
                'registration',
                'subs',
                'sub_members',
                'sub_posts',
            ])->default('karma')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table): void {
            $table->enum('type', [
                'karma',
                'posts',
                'post',
                'comments',
                'comment',
                'streak',
                'special',
                'action',
                'vote',
                'registration',
            ])->default('karma')->change();
        });
    }
};
