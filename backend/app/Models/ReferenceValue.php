<?php

namespace App\Models;

use App\Models\Concerns\ProtectsSystemRecords;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ReferenceValue extends Model
{
    use HasFactory, ProtectsSystemRecords;

    protected $fillable = ['reference_table_id', 'key', 'label', 'sort_order', 'is_system', 'is_active', 'version'];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty('key')) {
                throw new LogicException('Reference value key is immutable once created.');
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

    public function referenceTable(): BelongsTo
    {
        return $this->belongsTo(ReferenceTable::class);
    }

    /**
     * Merchant companies whose sector selection points at this value.
     * Seam: Epic 18.4 (workflow field definitions) and 18.5 (request field data)
     * will add further usage checks once those tables exist.
     */
    public function merchantCompanies(): HasMany
    {
        return $this->hasMany(MerchantCompany::class, 'sector_reference_value_id');
    }

    public function isInUse(): bool
    {
        return $this->merchantCompanies()->exists();
    }
}
