<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table): void {
            $table->id();

            $table->string('notification_type');
            $table->string('event_id');

            // Soft reference to users.id (NO hard FK by design). The outbox is a
            // permanent, immutable audit artifact: a row must survive deletion of the
            // recipient user and must be insertable for a resolved-but-not-yet-persisted
            // recipient. A cascading/blocking FK would violate both, so referential
            // integrity is enforced at the application layer instead.
            // Nullable: some sends (e.g. an OTP mid-recovery) may not map to a user row;
            // for auth sends the caller resolves the id so the unique index can dedup
            // (MySQL/SQLite treat NULLs as distinct — EmailDeliveryService guards that).
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->string('recipient_email');
            $table->string('channel');

            // Plain string (not enum) for forward-compat; allowed values are enforced
            // at the application layer by App\Enums\EmailDeliveryStatus.
            $table->string('status')->default('queued');

            $table->string('provider_message_id')->nullable();
            $table->text('rendered_subject')->nullable();
            $table->longText('rendered_body')->nullable();

            // FK to notification_template_versions is intentionally deferred to Story 15.3
            // (the referenced table does not exist yet); the column is added now so 15.4/15.5
            // can stamp it without another migration.
            $table->unsignedBigInteger('template_version_id')->nullable();

            $table->text('error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Idempotency guarantee (D2): one delivery per logical event/recipient/channel.
            $table->unique(['event_id', 'recipient_user_id', 'channel'], 'ed_event_recipient_channel_unique');
            $table->index('status', 'ed_status_idx');
            $table->index('notification_type', 'ed_notification_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
    }
};
