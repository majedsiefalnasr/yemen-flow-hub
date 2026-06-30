<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplateVersion extends Model
{
    protected $fillable = [
        'notification_template_id',
        'subject',
        'body',
        'changed_by',
        'is_active_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active_version' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active_version', true);
    }
}
