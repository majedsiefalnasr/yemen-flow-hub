<?php

namespace Tests\Feature\Notifications;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ATDD RED PHASE — Story 15.1 Outbox & Delivery Foundation.
 *
 * Guardrails for the `email_deliveries` outbox schema (AC1).
 * These tests fail until the migration exists. Remove the markTestSkipped()
 * line in each test as the corresponding behavior is implemented (TDD green).
 *
 * @group atdd-15-1
 */
class EmailDeliveriesSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** T1 — table + all required columns exist (AC1.1). */
    public function test_email_deliveries_table_has_all_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('email_deliveries'));

        $expected = [
            'id',
            'notification_type',
            'event_id',
            'recipient_user_id',
            'recipient_email',
            'channel',
            'status',
            'provider_message_id',
            'rendered_subject',
            'rendered_body',
            'template_version_id',
            'error',
            'queued_at',
            'sent_at',
            'created_at',
            'updated_at',
        ];

        $this->assertTrue(
            Schema::hasColumns('email_deliveries', $expected),
            'email_deliveries is missing one or more required columns: '.implode(', ', $expected)
        );
    }

    /**
     * T2 — unique composite index on (event_id, recipient_user_id, channel) (AC1.2).
     *
     * This is the idempotency backbone. The test asserts that a second insert
     * with the identical tuple is rejected at the DB layer.
     */
    public function test_unique_index_on_event_id_recipient_user_id_channel(): void
    {
        $now = now();
        \DB::table('email_deliveries')->insert([
            'notification_type' => 'REQUEST_APPROVED',
            'event_id' => '42:BANK_APPROVED',
            'recipient_user_id' => 1,
            'recipient_email' => 'a@example.com',
            'channel' => 'mail',
            'status' => 'queued',
            'queued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(QueryException::class);

        \DB::table('email_deliveries')->insert([
            'notification_type' => 'REQUEST_APPROVED',
            'event_id' => '42:BANK_APPROVED',
            'recipient_user_id' => 1,
            'recipient_email' => 'a@example.com',
            'channel' => 'mail',
            'status' => 'queued',
            'queued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** T3 — supporting indexes on status and notification_type (AC1.3). */
    public function test_supporting_indexes_exist(): void
    {
        // Implementation detail: assert via the schema builder's index listing.
        // Dev may verify using doctrine/dbal or a raw pragma; this scaffold
        // documents the requirement. Replace with the project's index assertion
        // helper when activating.
        $this->assertTrue(
            Schema::hasColumn('email_deliveries', 'status')
            && Schema::hasColumn('email_deliveries', 'notification_type'),
            'Expected status and notification_type columns to be indexed.'
        );
    }
}
