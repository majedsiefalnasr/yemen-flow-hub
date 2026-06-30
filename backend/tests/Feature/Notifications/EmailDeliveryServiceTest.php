<?php

namespace Tests\Feature\Notifications;

use App\Enums\EmailDeliveryStatus;
use App\Enums\NotificationType;
use App\Models\EmailDelivery;
use App\Models\NotificationTemplate;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ATDD RED PHASE — Story 15.1 Outbox & Delivery Foundation.
 *
 * Guardrails for the EmailDeliveryService reserve/finalize/markSent/markFailed
 * lifecycle (AC3). Fails until App\Services\Notifications\EmailDeliveryService
 * and App\Models\EmailDelivery exist.
 *
 * Design rule under test: reserve() is INSERT-FIRST. It must attempt the
 * insert and catch the unique-constraint violation, returning null on a
 * duplicate — NOT pre-check with a race-prone exists() query.
 *
 * @group atdd-15-1
 */
class EmailDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EmailDeliveryService
    {
        return app(EmailDeliveryService::class);
    }

    private function workflowType(): NotificationType
    {
        return NotificationType::REQUEST_APPROVED;
    }

    /** T9 — reserve() creates a queued row with queued_at set (AC3.10). */
    public function test_reserve_creates_queued_row(): void
    {
        $delivery = $this->service()->reserve(
            $this->workflowType(),
            '42:BANK_APPROVED',
            1,
            'creator@example.com',
            'mail'
        );

        $this->assertNotNull($delivery);
        $this->assertSame('queued', $delivery->status->value ?? $delivery->status);
        $this->assertNotNull($delivery->queued_at);
        $this->assertDatabaseHas('email_deliveries', [
            'event_id' => '42:BANK_APPROVED',
            'recipient_user_id' => 1,
            'channel' => 'mail',
            'status' => 'queued',
        ]);
    }

    /** T10 — duplicate (event_id, user_id, channel) returns null, no second row (AC3.10, AC5.17). */
    public function test_reserve_duplicate_returns_null_and_writes_no_second_row(): void
    {
        $first = $this->service()->reserve($this->workflowType(), '42:BANK_APPROVED', 1, 'c@example.com', 'mail');
        $this->assertNotNull($first);

        $second = $this->service()->reserve($this->workflowType(), '42:BANK_APPROVED', 1, 'c@example.com', 'mail');
        $this->assertNull($second, 'Duplicate reserve must return null.');

        $this->assertDatabaseCount('email_deliveries', 1);
    }

    /** T11 — differing keys create distinct rows (AC5.17). */
    public function test_reserve_differing_keys_create_distinct_rows(): void
    {
        $this->service()->reserve($this->workflowType(), '42:BANK_APPROVED', 1, 'c@example.com', 'mail');
        $this->service()->reserve($this->workflowType(), '42:BANK_APPROVED', 2, 'r@example.com', 'mail');     // diff user
        $this->service()->reserve($this->workflowType(), '99:BANK_APPROVED', 1, 'c@example.com', 'mail');     // diff event
        $this->service()->reserve($this->workflowType(), '42:BANK_APPROVED', 1, 'c@example.com', 'database'); // diff channel

        $this->assertDatabaseCount('email_deliveries', 4);
    }

    /**
     * T12 — reserve() is insert-first: it tolerates a concurrent duplicate
     * by catching the DB unique violation rather than relying on a prior read.
     *
     * We simulate the race by pre-inserting the row directly, then calling
     * reserve() with the same tuple — it must return null, not throw.
     */
    public function test_reserve_is_insert_first_and_swallows_unique_violation(): void
    {
        $now = now();
        DB::table('email_deliveries')->insert([
            'notification_type' => 'REQUEST_APPROVED',
            'event_id' => '42:BANK_APPROVED',
            'recipient_user_id' => 1,
            'recipient_email' => 'c@example.com',
            'channel' => 'mail',
            'status' => 'queued',
            'queued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $result = $this->service()->reserve($this->workflowType(), '42:BANK_APPROVED', 1, 'c@example.com', 'mail');
        $this->assertNull($result, 'reserve() must catch the unique violation and return null.');
        $this->assertDatabaseCount('email_deliveries', 1);
    }

    /** T13 — finalize() stores the rendered snapshot for a full-persist type (AC3.10). */
    public function test_finalize_stores_rendered_snapshot_for_full_type(): void
    {
        $delivery = $this->service()->reserve($this->workflowType(), '42:BANK_APPROVED', 1, 'c@example.com', 'mail');
        $this->service()->finalize($delivery, 'تمت الموافقة على طلبك', '<p>المرجع YFH-2026-000123</p>');

        $this->assertDatabaseHas('email_deliveries', [
            'event_id' => '42:BANK_APPROVED',
            'rendered_subject' => 'تمت الموافقة على طلبك',
        ]);
        $this->assertDatabaseHas('email_deliveries', [
            'event_id' => '42:BANK_APPROVED',
            'status' => 'queued', // finalize does not change status
        ]);
    }

    /** T17 — markSent / markFailed set terminal status + fields (AC3.10). */
    public function test_mark_sent_and_failed_set_terminal_status(): void
    {
        $sent = $this->service()->reserve($this->workflowType(), '1:BANK_APPROVED', 1, 'c@example.com', 'mail');
        $this->service()->markSent($sent, 'provider-msg-abc');
        $this->assertDatabaseHas('email_deliveries', [
            'event_id' => '1:BANK_APPROVED',
            'status' => 'sent',
            'provider_message_id' => 'provider-msg-abc',
        ]);
        $this->assertNotNull($sent->fresh()->sent_at);

        $failed = $this->service()->reserve($this->workflowType(), '2:BANK_APPROVED', 1, 'c@example.com', 'mail');
        $this->service()->markFailed($failed, 'SMTP connection refused');
        $this->assertDatabaseHas('email_deliveries', [
            'event_id' => '2:BANK_APPROVED',
            'status' => 'failed',
            'error' => 'SMTP connection refused',
        ]);
        $this->assertNotNull($failed->fresh()->failed_at);
    }

    public function test_service_owned_delivery_columns_are_not_mass_assignable(): void
    {
        $template = NotificationTemplate::query()->create([
            'notification_type' => 'REQUEST_APPROVED',
        ]);
        $version = $template->versions()->create([
            'subject' => 'Subject',
            'body' => 'Body',
            'is_active_version' => true,
        ]);

        $delivery = EmailDelivery::query()->create([
            'notification_type' => 'REQUEST_APPROVED',
            'event_id' => 'mass-assignment-probe',
            'recipient_user_id' => 1,
            'recipient_email' => 'creator@example.com',
            'channel' => 'mail',
            'status' => EmailDeliveryStatus::SENT,
            'provider_message_id' => 'provider-from-request',
            'template_version_id' => $version->id,
            'queued_at' => now(),
        ]);

        $stored = $delivery->fresh();

        $this->assertSame(EmailDeliveryStatus::QUEUED, $stored->status);
        $this->assertNull($stored->provider_message_id);
        $this->assertNull($stored->template_version_id);
    }
}
