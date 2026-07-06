<?php

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EngineNotificationActionUrlMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewrites_mappable_engine_request_action_urls_only(): void
    {
        DB::table('engine_notifications')->insert([
            [
                'id' => 9001,
                'type' => 'transition',
                'severity' => 'info',
                'title' => 'Stale route',
                'entity_type' => 'engine_request',
                'entity_id' => 321,
                'action_url' => '/requests/321',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 9002,
                'type' => 'transition',
                'severity' => 'info',
                'title' => 'Unmappable route',
                'entity_type' => 'engine_request',
                'entity_id' => null,
                'action_url' => '/requests/unknown',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 9003,
                'type' => 'workflow.published',
                'severity' => 'info',
                'title' => 'Other entity',
                'entity_type' => 'workflow_definition',
                'entity_id' => 77,
                'action_url' => '/requests/77',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require database_path('migrations/2026_07_06_000001_rewrite_engine_notification_action_urls.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('engine_notifications', [
            'id' => 9001,
            'action_url' => '/workflows/instances/321',
        ]);
        $this->assertDatabaseHas('engine_notifications', [
            'id' => 9002,
            'action_url' => '/requests/unknown',
        ]);
        $this->assertDatabaseHas('engine_notifications', [
            'id' => 9003,
            'action_url' => '/requests/77',
        ]);
    }
}
