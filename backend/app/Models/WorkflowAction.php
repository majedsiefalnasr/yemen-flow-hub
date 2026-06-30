<?php

namespace App\Models;

use App\Enums\WorkflowActionKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class WorkflowAction extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'kind', 'is_active', 'is_system', 'version'];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty('code')) {
                throw new LogicException('Workflow action code is immutable once created.');
            }
        });

        static::deleting(function (self $model): void {
            if ($model->isProtected()) {
                throw new LogicException('System workflow actions are protected from deletion.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => WorkflowActionKind::class,
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function isProtected(): bool
    {
        return (bool) $this->is_system;
    }
}
