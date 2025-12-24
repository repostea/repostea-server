<?php

declare(strict_types=1);

use App\Models\ActivityPubSubSettings;
use App\Models\Sub;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * Enable federation for all existing subs.
     */
    public function up(): void
    {
        $subs = Sub::all();

        foreach ($subs as $sub) {
            ActivityPubSubSettings::updateOrCreate(
                ['sub_id' => $sub->id],
                [
                    'federation_enabled' => true,
                    'auto_announce_posts' => true,
                ],
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * Disable federation for all subs.
     */
    public function down(): void
    {
        ActivityPubSubSettings::query()->update([
            'federation_enabled' => false,
        ]);
    }
};
