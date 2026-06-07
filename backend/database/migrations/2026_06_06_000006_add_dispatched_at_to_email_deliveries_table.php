<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story 15.4 review patch: adds a claim marker so the queued SendEmailDelivery job
 * can atomically reserve a row before transport. A retry after a mid-send worker
 * crash finds dispatched_at already set and stops, preventing a duplicate send
 * (notably duplicate OTP/reset emails). Status stays `queued` until the job marks
 * it sent/failed, so the EmailDeliveryStatus enum (queued/sent/failed/bounced)
 * is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->timestamp('dispatched_at')->nullable()->after('queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropColumn('dispatched_at');
        });
    }
};
