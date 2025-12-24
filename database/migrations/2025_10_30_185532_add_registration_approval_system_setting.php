<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('system_settings')->insert([
            'key' => 'registration_approval',
            'value' => 'none',
            'type' => 'string',
            'description' => 'Controls whether new user registrations require admin approval. Options: none (no approval needed), required (admin must approve)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')->where('key', 'registration_approval')->delete();
    }
};
