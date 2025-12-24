<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('posts', static function (Blueprint $table): void {
            $table->boolean('is_anonymous')->default(false)->after('is_original');
        });

        Schema::table('comments', static function (Blueprint $table): void {
            $table->boolean('is_anonymous')->default(false)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('posts', static function (Blueprint $table): void {
            $table->dropColumn('is_anonymous');
        });

        Schema::table('comments', static function (Blueprint $table): void {
            $table->dropColumn('is_anonymous');
        });
    }
};
