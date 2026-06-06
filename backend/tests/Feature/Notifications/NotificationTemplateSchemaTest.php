<?php

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationTemplateSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_template_store_tables_have_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('notification_templates'));
        $this->assertTrue(Schema::hasTable('notification_template_versions'));

        $this->assertTrue(Schema::hasColumns('notification_templates', [
            'id',
            'notification_type',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('notification_template_versions', [
            'id',
            'notification_template_id',
            'subject',
            'body',
            'changed_by',
            'is_active_version',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_email_deliveries_template_version_id_has_deferred_foreign_key(): void
    {
        $this->assertTrue(Schema::hasColumn('email_deliveries', 'template_version_id'));

        $connection = Schema::getConnection();
        $foreignKeys = $connection->getSchemaBuilder()->getForeignKeys('email_deliveries');

        $hasTemplateVersionForeignKey = collect($foreignKeys)->contains(
            fn (array $foreignKey): bool => in_array('template_version_id', $foreignKey['columns'] ?? [], true)
                && ($foreignKey['foreign_table'] ?? null) === 'notification_template_versions'
        );

        $this->assertTrue($hasTemplateVersionForeignKey);
    }
}
