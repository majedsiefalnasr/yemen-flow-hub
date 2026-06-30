<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineNotification extends Model
{
    protected $table = 'engine_notifications';

    protected $fillable = [
        'type',
        'severity',
        'title',
        'body',
        'entity_type',
        'entity_id',
        'action_url',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class, 'notification_id');
    }
}
