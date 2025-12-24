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
        if (! Schema::hasColumn('posts', 'sub_id')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->unsignedBigInteger('sub_id')->nullable()->after('user_id');
                $table->foreign('sub_id')->references('id')->on('subs')->onDelete('set null');
                $table->index('sub_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropForeign(['sub_id']);
            $table->dropIndex(['sub_id']);
            $table->dropColumn('sub_id');
        });
    }
};
