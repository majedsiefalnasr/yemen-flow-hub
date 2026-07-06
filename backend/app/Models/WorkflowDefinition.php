<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class WorkflowDefinition extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'description', 'is_active', 'version'];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty('code')) {
                throw new LogicException('Workflow definition code is immutable once created.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }
}
