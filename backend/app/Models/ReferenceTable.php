<?php

namespace App\Models;

use App\Models\Concerns\ProtectsSystemRecords;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ReferenceTable extends Model
{
    use HasFactory, ProtectsSystemRecords;

    protected $fillable = ['key', 'label', 'sort_order', 'is_system', 'is_active', 'version'];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty('key')) {
                throw new LogicException('Reference table key is immutable once created.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'version' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(ReferenceValue::class);
    }

    public function isInUse(): bool
    {
        return $this->values()->exists();
    }
}
