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
        Schema::create('agora_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('agora_messages')->onDelete('cascade');
            $table->text('content');
            $table->integer('votes_count')->default(0);
            $table->integer('replies_count')->default(0);
            $table->boolean('is_anonymous')->default(false);
            $table->string('language_code', 2)->default('es');
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('parent_id');
            $table->index('created_at');
            $table->index('votes_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agora_messages');
    }
};
