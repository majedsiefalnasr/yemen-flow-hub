<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraderOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'trader_id',
        'full_name',
        'ownership_percentage',
        'nationality',
        'identification_number',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percentage' => 'decimal:2',
        ];
    }

    public function trader(): BelongsTo
    {
        return $this->belongsTo(Trader::class);
    }
}
