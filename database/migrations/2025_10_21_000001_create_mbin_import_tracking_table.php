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
        Schema::create('mbin_import_tracking', static function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type')->index(); // 'user', 'magazine', 'entry', 'entry_comment', 'entry_vote'
            $table->unsignedBigInteger('mbin_id')->index(); // ID del registro en Mbin
            $table->unsignedBigInteger('repostea_id'); // ID del registro creado en Repostea
            $table->timestamp('imported_at');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable(); // Additional data useful for debugging

            $table->unique(['entity_type', 'mbin_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mbin_import_tracking');
    }
};
