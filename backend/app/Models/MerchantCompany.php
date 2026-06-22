<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'commercial_registration_number',
        'commercial_registration_expiry',
        'sector_reference_value_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'commercial_registration_expiry' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
