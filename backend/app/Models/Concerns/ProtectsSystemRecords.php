<?php

namespace App\Models\Concerns;

use LogicException;

trait ProtectsSystemRecords
{
    protected static function bootProtectsSystemRecords(): void
    {
        static::deleting(function (self $model): void {
            if ($model->isProtected()) {
                throw new LogicException('System governance records are protected from deletion.');
            }
        });

        static::updating(function (self $model): void {
            if (! $model->isProtected()) {
                return;
            }

            // System rows may be renamed (and version-bumped), but their identity
            // (`code`, `is_system`) is immutable and they cannot be deactivated.
            if ($model->isDirty('code') || $model->isDirty('is_system')) {
                throw new LogicException('System governance records cannot change their code or system flag.');
            }
            if ($model->isDirty('is_active') && $model->is_active === false) {
                throw new LogicException('System governance records cannot be deactivated.');
            }
        });
    }

    public function isProtected(): bool
    {
        return (bool) $this->is_system;
    }
}
