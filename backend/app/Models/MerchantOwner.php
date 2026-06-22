<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'ownership_percentage',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percentage' => 'decimal:2',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
