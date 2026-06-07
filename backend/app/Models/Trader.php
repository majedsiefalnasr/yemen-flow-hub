<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trader extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_number',
        'trader_name',
        'tax_card_expiry',
        'commercial_registration_number',
        'commercial_registration_expiry',
    ];

    protected function casts(): array
    {
        return [
            'tax_card_expiry' => 'date',
            'commercial_registration_expiry' => 'date',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(TraderCompany::class);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(TraderOwner::class);
    }
}
