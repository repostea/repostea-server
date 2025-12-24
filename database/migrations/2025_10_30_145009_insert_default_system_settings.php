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
        $now = now();

        DB::table('system_settings')->insert([
            [
                'key' => 'registration_mode',
                'value' => 'invite_only',
                'type' => 'string',
                'description' => 'Registration mode: open (free registration), invite_only (invitation required), closed (registration disabled)',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'email_verification',
                'value' => 'optional',
                'type' => 'string',
                'description' => 'Email verification: required (must verify to use account), optional (verification gives benefits), disabled (no verification)',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'guest_access',
                'value' => 'enabled',
                'type' => 'string',
                'description' => 'Guest access: enabled (allow guest login), disabled (no guest access)',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'registration_mode',
            'email_verification',
            'guest_access',
        ])->delete();
    }
};
