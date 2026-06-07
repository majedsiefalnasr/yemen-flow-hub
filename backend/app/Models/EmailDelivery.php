<?php

namespace App\Models;

use App\Enums\EmailDeliveryStatus;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditable outbox row for a single outbound email (Epic 15).
 *
 * Rows are written ONLY by {@see EmailDeliveryService}.
 */
class EmailDelivery extends Model
{
    protected $fillable = [
        'notification_type',
        'event_id',
        'recipient_user_id',
        'recipient_email',
        'channel',
        'queued_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmailDeliveryStatus::class,
            'queued_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'failed_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
