<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $count = 0;

        DB::table('engine_notifications')
            ->where('entity_type', 'engine_request')
            ->whereNotNull('entity_id')
            ->where('action_url', 'like', '/requests/%')
            ->orderBy('id')
            ->select(['id', 'entity_id'])
            ->chunkById(100, function ($notifications) use (&$count): void {
                foreach ($notifications as $notification) {
                    DB::table('engine_notifications')
                        ->where('id', $notification->id)
                        ->update([
                            'action_url' => "/workflows/instances/{$notification->entity_id}",
                            'updated_at' => now(),
                        ]);
                    $count++;
                }
            });

        Log::info('Rewrote stale engine notification action URLs.', [
            'rows' => $count,
        ]);
    }

    public function down(): void
    {
        // The new route is the valid application route. Keep rewritten URLs intact.
    }
};
