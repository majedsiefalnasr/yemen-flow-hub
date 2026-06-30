<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'notification_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'notification_type' => NotificationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NotificationTemplateVersion::class);
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(NotificationTemplateVersion::class)
            ->where('is_active_version', true)
            ->latestOfMany();
    }

    public function createActiveVersion(string $subject, string $body, ?int $changedBy = null): NotificationTemplateVersion
    {
        return DB::transaction(function () use ($subject, $body, $changedBy): NotificationTemplateVersion {
            $template = self::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if (! $template) {
                throw (new ModelNotFoundException)->setModel(self::class, [$this->getKey()]);
            }

            $template->versions()->where('is_active_version', true)->update([
                'is_active_version' => false,
            ]);

            return $template->versions()->create([
                'subject' => $subject,
                'body' => $body,
                'changed_by' => $changedBy,
                'is_active_version' => true,
            ]);
        });
    }
}
