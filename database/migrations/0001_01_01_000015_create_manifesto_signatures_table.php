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
        Schema::create('manifesto_signatures', static function (Blueprint $table): void {
            $table->id();
            $table->string('alias');
            $table->string('ip_hash');
            $table->string('lang', 2)->default('es');
            $table->timestamps();

            $table->unique('ip_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifesto_signatures');
    }
};
